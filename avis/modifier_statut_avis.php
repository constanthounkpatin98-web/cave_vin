<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}

if(isset($_GET["id_avis"]) && isset($_GET["statut"]))
{
    $id_avis = $_GET["id_avis"];
    $statut  = $_GET["statut"];

    $statuts_valides = ["Publié", "En attente", "Masqué"];

    if(!in_array($statut, $statuts_valides))
    {
        header("Location: liste_avis.php");
        exit();
    }

    $requete = $connexion->prepare("UPDATE avis SET statut = ? WHERE id_avis = ?");
    $requete->execute([$statut, $id_avis]);

    header("Location: liste_avis.php?modifier=ok");
    exit();
}
else
{
    header("Location: liste_avis.php");
    exit();
}
