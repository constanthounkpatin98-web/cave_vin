<?php
/*
    Endpoint AJAX appelé par le JS de paiement_en_cours.php après le délai
    d'attente simulé, pour VRAIMENT valider le paiement en base de données
    (jusqu'ici, seule une animation JS simulait le succès, sans jamais
    toucher la base : le statut restait bloqué sur "En attente").

    Ce fichier :
      1. Met à jour paiement.statut -> 'Validé'
      2. Crée une notification pour l'administrateur (déclenche la sonnerie)
*/

session_start();

require_once("../connexion.php");

header("Content-Type: application/json");

if (!isset($_SESSION["client_id"])) {
    http_response_code(401);
    echo json_encode(["succes" => false, "erreur" => "non_authentifie"]);
    exit();
}

$id_commande = isset($_POST["id_commande"]) ? (int) $_POST["id_commande"] : 0;
$id_client   = $_SESSION["client_id"];

if ($id_commande <= 0) {
    http_response_code(400);
    echo json_encode(["succes" => false, "erreur" => "id_commande_invalide"]);
    exit();
}

try {

    //===============================================
    // Vérifier que la commande appartient bien au client
    //===============================================

    $requete_commande = $connexion->prepare("
        SELECT * FROM commande WHERE id_commande = ? AND id_client = ?
    ");
    $requete_commande->execute([$id_commande, $id_client]);
    $commande = $requete_commande->fetch();

    if (!$commande) {
        http_response_code(403);
        echo json_encode(["succes" => false, "erreur" => "commande_introuvable"]);
        exit();
    }

    //===============================================
    // Récupérer le dernier paiement de cette commande
    //===============================================

    $requete_paiement = $connexion->prepare("
        SELECT * FROM paiement WHERE id_commande = ? ORDER BY id_paiement DESC LIMIT 1
    ");
    $requete_paiement->execute([$id_commande]);
    $paiement = $requete_paiement->fetch();

    if (!$paiement) {
        http_response_code(404);
        echo json_encode(["succes" => false, "erreur" => "paiement_introuvable"]);
        exit();
    }

    // Si déjà validé (double appel, rechargement, etc.), on ne refait rien
    if ($paiement["statut"] === "Validé") {
        echo json_encode(["succes" => true, "deja_valide" => true]);
        exit();
    }

    //===============================================
    // Valider le paiement
    //===============================================

    $requete_maj = $connexion->prepare("
        UPDATE paiement SET statut = 'Validé' WHERE id_paiement = ?
    ");
    $requete_maj->execute([$paiement["id_paiement"]]);

    //===============================================
    // Notification pour l'administrateur (sonnerie)
    //===============================================

    $requete_client = $connexion->prepare("SELECT nom, prenom FROM client WHERE id_client = ?");
    $requete_client->execute([$id_client]);
    $client = $requete_client->fetch();

    $nom_complet_client = $client
        ? trim(($client["prenom"] ?? "") . " " . ($client["nom"] ?? ""))
        : "Un client";

    $titre_notif   = "Nouveau paiement reçu";
    $message_notif = $nom_complet_client . " a payé " . number_format($paiement["montant"], 0, ',', ' ')
                    . " FCFA (" . $paiement["mode_paiement"] . ") pour la commande #" . $id_commande
                    . ". Réf : " . $paiement["reference_transaction"];

    $requete_notif = $connexion->prepare("
        INSERT INTO notification (titre, message, statut, id_client, date_envoi)
        VALUES (?, ?, 'Non lue', ?, NOW())
    ");
    $requete_notif->execute([$titre_notif, $message_notif, $id_client]);

    echo json_encode(["succes" => true, "deja_valide" => false]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["succes" => false, "erreur" => "erreur_serveur"]);
}
