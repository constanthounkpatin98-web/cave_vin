<?php

session_start();

require_once("../connexion.php");

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
// Confirmer le paiement en attente
//===============================================

$requete_maj = $connexion->prepare("

    UPDATE paiement
    SET statut = 'Réussi', date_paiement = NOW()
    WHERE id_commande = ? AND statut = 'En attente'

");

$requete_maj->execute([$id_commande]);


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
