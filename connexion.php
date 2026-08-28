<?php

/*
|--------------------------------------------------------------------------
| connexion.php
|--------------------------------------------------------------------------
| Ce fichier établit la connexion entre l'application PHP
| et la base de données MySQL à l'aide de PDO.
|--------------------------------------------------------------------------
*/


//==============================
// Paramètres de connexion
//==============================

// Nom du serveur MySQL
$serveur = "localhost";

// Nom de la base de données
$base_de_donnees = "plateforme";

// Nom d'utilisateur MySQL
$utilisateur = "root";

// Mot de passe MySQL
$mot_de_passe = "";



try
{

    //=====================================================
    // Création de la connexion PDO
    //=====================================================

    $connexion = new PDO("mysql:host=$serveur;dbname=$base_de_donnees;charset=utf8",$utilisateur,$mot_de_passe );
    //=====================================================
    // Activation du mode Exception
    //=====================================================

    $connexion->setAttribute(

        PDO::ATTR_ERRMODE,

        PDO::ERRMODE_EXCEPTION

    );



    //=====================================================
    // Définition du mode de récupération
    //=====================================================

    $connexion->setAttribute(

        PDO::ATTR_DEFAULT_FETCH_MODE,

        PDO::FETCH_ASSOC

    );



}

catch(PDOException $erreur)
{

    die(

        "<h2 style='color:red;text-align:center;margin-top:50px;'>

        Impossible de se connecter à la base de données.<br><br>

        Erreur : ".$erreur->getMessage()."

        </h2>"

    );

}

?>
