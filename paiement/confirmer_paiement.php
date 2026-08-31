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
// Récupération de la commande et du paiement en attente
//===============================================

$id_commande = isset($_GET["id_commande"]) ? (int) $_GET["id_commande"] : 0;

$requete_commande = $connexion->prepare("SELECT * FROM commande WHERE id_commande = ? AND id_client = ?");
$requete_commande->execute([$id_commande, $_SESSION["client_id"]]);
$commande = $requete_commande->fetch();

if (!$commande) {
    header("Location: ../client/accueil_client.php");
    exit();
}


//===============================================
// Récupérer le paiement en attente AVANT la mise à jour
// (on a besoin du mode/montant/référence pour la notification)
//===============================================

$requete_paiement = $connexion->prepare("SELECT * FROM paiement WHERE id_commande = ? AND statut = 'En attente'");
$requete_paiement->execute([$id_commande]);
$paiement = $requete_paiement->fetch();


//===============================================
// Confirmer le paiement en attente
//
// CORRECTION : le statut écrit ici était 'Réussi', une valeur qui
// n'existe même pas dans l'ENUM de la colonne `paiement`.statut
// ('En attente','Validé','Échoué','Remboursé'). Résultat : la mise
// à jour échouait silencieusement et confirmation_paiement.php,
// qui exige statut = 'Validé', rejetait systématiquement ce paiement.
//===============================================

$requete_maj = $connexion->prepare("

    UPDATE paiement
    SET statut = 'Validé', date_paiement = NOW()
    WHERE id_commande = ? AND statut = 'En attente'

");

$requete_maj->execute([$id_commande]);


//===============================================
// Attribution des points de fidélité
// (protégée contre la double attribution dans la fonction elle-même)
//===============================================

if ($paiement) {
    attribuer_points_fidelite($connexion, $_SESSION["client_id"], $id_commande, (float) $paiement["montant"]);
}


//===============================================
// Notification pour l'administrateur (sonnerie)
//===============================================

if ($paiement) {

    try {

        $requete_client = $connexion->prepare("SELECT nom, prenom FROM client WHERE id_client = ?");
        $requete_client->execute([$_SESSION["client_id"]]);
        $client = $requete_client->fetch();

        $nom_complet_client = $client
            ? trim(($client["prenom"] ?? "") . " " . ($client["nom"] ?? ""))
            : "Un client";

        $titre_notif   = "Nouveau paiement reçu";
        $message_notif = $nom_complet_client . " a payé " . number_format($paiement["montant"], 0, ',', ' ')
                        . " FCFA (" . $paiement["mode_paiement"] . ") pour la commande #" . $id_commande
                        . ". Réf : " . $paiement["reference_transaction"];

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

}


//===============================================
// Vider le panier une fois le paiement confirmé
//===============================================

$_SESSION["panier"] = [];


//===============================================
// Redirection vers la confirmation
//===============================================

header("Location: confirmation_paiement.php?id_commande=" . $id_commande);
exit();

?>
