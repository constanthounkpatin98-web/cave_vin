<?php

date_default_timezone_set('Africa/Porto-Novo');

$serveur = "localhost";
$base_de_donnees = "plateforme";
$utilisateur = "root";
$mot_de_passe = "";

try
{
    $connexion = new PDO(
        "mysql:host=$serveur;dbname=$base_de_donnees;charset=utf8",
        $utilisateur,
        $mot_de_passe
    );

    $connexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $connexion->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    // Aligne aussi l'horloge MySQL sur le même fuseau horaire que PHP,
    // pour que NOW() côté base et date() côté PHP soient toujours cohérents.
    $connexion->exec("SET time_zone = '+01:00'");
}
catch(PDOException $erreur)
{
    die("Impossible de se connecter à la base de données.");
}
?>
