<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}

if(isset($_GET["id_avis"]))
{
    $id_avis = $_GET["id_avis"];

    $requete = $connexion->prepare("DELETE FROM avis WHERE id_avis = ?");
    $requete->execute([$id_avis]);

    header("Location: liste_avis.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_avis.php");
    exit();
}
