<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}

if(isset($_GET["id_promotion"]))
{
    $id_promotion = $_GET["id_promotion"];

    $requete = $connexion->prepare("DELETE FROM promotion WHERE id_promotion = ?");
    $requete->execute([$id_promotion]);

    header("Location: liste_promotion.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_promotion.php");
    exit();
}
