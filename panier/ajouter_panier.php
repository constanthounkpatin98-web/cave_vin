<?php

session_start();

require_once("../connexion.php");

if(!isset($_POST["id_vin"]))
{
    header("Location: ../vin/liste_vin.php");
    exit();
}

$id_vin   = $_POST["id_vin"];
$quantite = isset($_POST["quantite"]) ? (int)$_POST["quantite"] : 1;

if($quantite < 1)
{
    $quantite = 1;
}

//===============================================
// Vérifier que le vin existe et a du stock
//===============================================

$requete = $connexion->prepare("SELECT * FROM vin WHERE id_vin = ?");
$requete->execute([$id_vin]);
$vin = $requete->fetch();

if(!$vin || $vin["statut"] != "Disponible" || $vin["quantite_stock"] < 1)
{
    header("Location: panier.php?erreur=indisponible");
    exit();
}

//===============================================
// Initialiser le panier si nécessaire
//===============================================

if(!isset($_SESSION["panier"]))
{
    $_SESSION["panier"] = [];
}

//===============================================
// Ajouter ou incrémenter la quantité (sans dépasser le stock)
//===============================================

if(isset($_SESSION["panier"][$id_vin]))
{
    $_SESSION["panier"][$id_vin] += $quantite;
}
else
{
    $_SESSION["panier"][$id_vin] = $quantite;
}

if($_SESSION["panier"][$id_vin] > $vin["quantite_stock"])
{
    $_SESSION["panier"][$id_vin] = $vin["quantite_stock"];
}

header("Location: panier.php?ajout=ok");
exit();
