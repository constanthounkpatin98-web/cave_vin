<?php

session_start();

require_once("../connexion.php");

if(!isset($_POST["id_vin"]) || !isset($_SESSION["panier"]))
{
    header("Location: panier.php");
    exit();
}

$id_vin   = $_POST["id_vin"];
$quantite = (int)$_POST["quantite"];

if($quantite < 1)
{
    unset($_SESSION["panier"][$id_vin]);
    header("Location: panier.php");
    exit();
}

// Vérifier que la quantité demandée ne dépasse pas le stock disponible

$requete = $connexion->prepare("SELECT quantite_stock FROM vin WHERE id_vin = ?");
$requete->execute([$id_vin]);
$vin = $requete->fetch();

if($vin && $quantite > $vin["quantite_stock"])
{
    $quantite = $vin["quantite_stock"];
}

if(isset($_SESSION["panier"][$id_vin]))
{
    $_SESSION["panier"][$id_vin] = $quantite;
}

header("Location: panier.php");
exit();
