<?php

require_once("../connexion.php");

if(isset($_GET["id_client"]))
{
    $id_client = $_GET["id_client"];

    $requete = $connexion->prepare("DELETE FROM client WHERE id_client = ?");
    $requete->execute([$id_client]);

    header("Location: liste_client.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_client.php");
    exit();
}
