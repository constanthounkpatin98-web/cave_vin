<?php

session_start();

require_once("../connexion.php");
require_once("../client/fonctions_fidelite.php");

//===============================================
// Sécurité : client connecté
//===============================================

if (!isset($_SESSION["client_id"])) {
    header("Location: ../client/connexion_client.php");
    exit();
}


//===============================================
// Vérification de la méthode et des données reçues
//===============================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../panier/panier.php");
    exit();
}

$id_commande   = isset($_POST["id_commande"]) ? (int) $_POST["id_commande"] : 0;
$montant       = isset($_POST["montant"]) ? (float) $_POST["montant"] : 0;
$mode_paiement = isset($_POST["mode_paiement"]) ? trim($_POST["mode_paiement"]) : "";
$numero_momo   = isset($_POST["numero_momo"]) ? trim($_POST["numero_momo"]) : "";

$modes_autorises = ["MTN Mobile Money", "Moov Money"];

if (
    $id_commande <= 0
    || $montant <= 0
    || !in_array($mode_paiement, $modes_autorises)
    || !preg_match("/^[0-9\s]{8,12}$/", $numero_momo)
) {
    header("Location: paiement.php?id_commande=" . $id_commande . "&erreur=numero");
    exit();
}


//===============================================
// Vérifier que la commande appartient bien au client
//===============================================

$requete_commande = $connexion->prepare("

    SELECT *
    FROM commande
    WHERE id_commande = ? AND id_client = ?

");

$requete_commande->execute([$id_commande, $_SESSION["client_id"]]);

$commande = $requete_commande->fetch();

if (!$commande) {
    header("Location: ../panier/panier.php");
    exit();
}


//===============================================
// Générer une référence de transaction unique
//===============================================

$reference_transaction = "MP" . date("YmdHis") . rand(1000, 9999);


//===============================================
// Enregistrement du paiement
//===============================================

$requete_insertion = $connexion->prepare("

    INSERT INTO paiement (
        date_paiement,
        mode_paiement,
        montant,
        statut,
        reference_transaction,
        id_commande
    ) VALUES (
        NOW(),
        ?,
        ?,
        'Validé',
        ?,
        ?
    )

");

$requete_insertion->execute([
    $mode_paiement,
    $montant,
    $reference_transaction,
    $id_commande,
]);


//===============================================
// Attribution des points de fidélité
// (protégée contre la double attribution dans la fonction elle-même)
//===============================================

attribuer_points_fidelite($connexion, $_SESSION["client_id"], $id_commande, $montant);


//===============================================
// Notification pour l'administrateur (sonnerie)
//===============================================

try {

    $requete_client = $connexion->prepare("SELECT nom, prenom FROM client WHERE id_client = ?");
    $requete_client->execute([$_SESSION["client_id"]]);
    $client = $requete_client->fetch();

    $nom_complet_client = $client
        ? trim(($client["prenom"] ?? "") . " " . ($client["nom"] ?? ""))
        : "Un client";

    $titre_notif   = "Nouveau paiement reçu";
    $message_notif = $nom_complet_client . " a payé " . number_format($montant, 0, ',', ' ')
                    . " FCFA (" . $mode_paiement . ") pour la commande #" . $id_commande
                    . ". Réf : " . $reference_transaction;

    $requete_notif = $connexion->prepare("

        INSERT INTO notification (
            titre,
            message,
            statut,
            id_client,
            date_envoi
        ) VALUES (
            ?,
            ?,
            'Non lue',
            ?,
            NOW()
        )

    ");

    $requete_notif->execute([
        $titre_notif,
        $message_notif,
        $_SESSION["client_id"],
    ]);

} catch (PDOException $e) {
    // On ne bloque jamais le paiement si la notification échoue
}


//===============================================
// Vider le panier après paiement réussi
//===============================================

$_SESSION["panier"] = [];


//===============================================
// Redirection vers la confirmation
//===============================================

header("Location: confirmation_paiement.php?id_commande=" . $id_commande);
exit();

?>
