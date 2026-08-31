<?php

session_start();

require_once("../connexion.php");
require_once("fonctions_fidelite.php");

//===============================================
// Sécurité : client connecté
//===============================================

if (!isset($_SESSION["client_id"])) {
    header("Location: connexion_client.php");
    exit();
}

//===============================================
// Sécurité : uniquement POST
//===============================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ma_fidelite.php");
    exit();
}

$id_client = $_SESSION["client_id"];
$id_vin    = isset($_POST["id_vin"]) ? (int) $_POST["id_vin"] : 0;

if ($id_vin <= 0) {
    $_SESSION["fidelite_message_erreur"] = "Vin invalide.";
    header("Location: ma_fidelite.php");
    exit();
}

//===============================================
// Toute la logique de vérification (points, vin, stock, prix)
// est faite côté serveur dans utiliser_recompense_fidelite() —
// on ne fait jamais confiance aux données du navigateur.
//===============================================

$resultat = utiliser_recompense_fidelite($connexion, $id_client, $id_vin);

if ($resultat["succes"]) {

    $_SESSION["fidelite_message_succes"] = "Félicitations ! Vous avez obtenu \""
        . $resultat["vin"]["nom_vin"] . "\" grâce à vos points de fidélité.";

} else {

    $messages_erreur = [
        "points_insuffisants" => "Vous n'avez pas assez de points pour cette récompense.",
        "vin_introuvable"     => "Ce vin n'existe pas.",
        "vin_indisponible"    => "Ce vin n'est plus disponible.",
        "stock_epuise"        => "Ce vin n'est plus en stock.",
        "vin_trop_cher"       => "Ce vin dépasse le plafond autorisé de 10 000 FCFA pour une récompense.",
        "erreur_serveur"      => "Une erreur est survenue, merci de réessayer.",
    ];

    $_SESSION["fidelite_message_erreur"] = $messages_erreur[$resultat["erreur"]]
        ?? "Impossible d'utiliser cette récompense pour le moment.";

}

header("Location: ma_fidelite.php");
exit();

?>
