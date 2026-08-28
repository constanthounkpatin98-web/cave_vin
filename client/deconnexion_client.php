<?php

session_start();

//===============================================
// Suppression des données de session liées au client
//===============================================

unset($_SESSION["client_id"]);
unset($_SESSION["client_nom"]);
unset($_SESSION["client_prenom"]);
unset($_SESSION["client_email"]);
unset($_SESSION["panier"]);

//===============================================
// Destruction complète de la session
//===============================================

session_unset();
session_destroy();

//===============================================
// Redirection vers l'accueil client
//===============================================

header("Location: accueil_client.php");
exit();
