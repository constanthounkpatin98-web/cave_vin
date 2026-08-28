<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: connexion_admin.php");
    exit();
}

if(isset($_GET["id_admin"]))
{
    $id_admin = $_GET["id_admin"];

    // Empêcher un administrateur de se supprimer lui-même
    if($id_admin == $_SESSION["admin_id"])
    {
        header("Location: liste_admin.php?erreur=auto");
        exit();
    }

    $requete = $connexion->prepare("DELETE FROM administrateur WHERE id_admin = ?");
    $requete->execute([$id_admin]);

    header("Location: liste_admin.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_admin.php");
    exit();
}
