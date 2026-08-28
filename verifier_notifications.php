<?php
/*
    Endpoint AJAX appelé en boucle (polling) par toutes les pages admin
    pour savoir s'il y a de nouvelles notifications (ex: nouveau paiement)
    et déclencher la sonnerie côté navigateur.

    A placer à la RACINE du projet, au même niveau que connexion.php,
    car il est appelé depuis chaque dossier admin via "../verifier_notifications.php".
*/

session_start();

require_once("connexion.php");

header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode(["erreur" => "non_authentifie"]);
    exit();
}

try {

    $total_non_lues = $connexion->query("
        SELECT COUNT(*) AS total
        FROM notification
        WHERE statut = 'Non lue'
    ")->fetch()["total"];

    $derniere_notif = $connexion->query("
        SELECT id_notification, titre, message, date_envoi
        FROM notification
        ORDER BY date_envoi DESC
        LIMIT 1
    ")->fetch();

    echo json_encode([
        "non_lues"      => (int) $total_non_lues,
        "dernier_id"    => $derniere_notif ? (int) $derniere_notif["id_notification"] : 0,
        "dernier_titre" => $derniere_notif ? $derniere_notif["titre"] : "",
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erreur" => "requete_echouee"]);
}
