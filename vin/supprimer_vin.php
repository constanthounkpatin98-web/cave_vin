<?php

require_once("../connexion.php");

if(isset($_GET["id_vin"]))
{
    $id_vin = $_GET["id_vin"];

    $requete = $connexion->prepare("DELETE FROM vin WHERE id_vin = ?");
    $requete->execute([$id_vin]);

    header("Location: liste_vin.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_vin.php");
    exit();
}
