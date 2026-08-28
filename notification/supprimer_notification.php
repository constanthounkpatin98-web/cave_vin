<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}

if(isset($_GET["id_notification"]))
{
    $id_notification = $_GET["id_notification"];

    $requete = $connexion->prepare("DELETE FROM notification WHERE id_notification = ?");
    $requete->execute([$id_notification]);

    header("Location: liste_notification.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_notification.php");
    exit();
}
