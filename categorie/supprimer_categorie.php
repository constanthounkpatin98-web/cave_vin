<?php

require_once("../connexion.php");

if(isset($_GET["id_categorie"]))
{
    $id_categorie = $_GET["id_categorie"];

    $requete = $connexion->prepare("DELETE FROM categorie WHERE id_categorie = ?");
    $requete->execute([$id_categorie]);

    header("Location: liste_categorie.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_categorie.php");
    exit();
}
