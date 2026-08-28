<?php

//===============================================================
// CONFIGURATION DES NOTIFICATIONS (SMS + EMAIL)
// Remplis ces informations avec tes propres identifiants
// avant de mettre le site en ligne.
//===============================================================


//---------------------------------------------------------------
// SMS PARTNER BENIN (https://smspartner.africa/bj/)
//---------------------------------------------------------------
// 1. Crée un compte gratuit sur https://my.smspartner.fr/inscription
//    (20 SMS offerts pour tester, aucun engagement)
// 2. Récupère ta clé API dans "Mon compte > Clé API"
// 3. Colle-la ci-dessous

define("SMS_API_URL", "https://api.smspartner.fr/v1/send");
define("SMS_API_KEY", "TA_CLE_API_SMSPARTNER");
define("SMS_SENDER",  "CaveAVins");   // 11 caractères max, sans espace ni accent
define("SMS_GAMME",   1);             // 1 = SMS classique


//---------------------------------------------------------------
// GMAIL SMTP
//---------------------------------------------------------------
// IMPORTANT : il faut un "mot de passe d'application" Gmail,
// PAS ton mot de passe Gmail normal (Gmail le refusera sinon).
//
// Comment le créer :
// 1. Active la validation en 2 étapes sur ton compte Google
//    (obligatoire pour pouvoir créer un mot de passe d'application)
// 2. Va sur https://myaccount.google.com/apppasswords
// 3. Crée un mot de passe pour "Mail" / "Autre (nom personnalisé)"
// 4. Google te donne un code à 16 caractères -> colle-le ci-dessous

define("SMTP_HOST",      "smtp.gmail.com");
define("SMTP_PORT",      587);
define("SMTP_USER",      "tonadresse@gmail.com");
define("SMTP_PASS",      "xxxx xxxx xxxx xxxx"); // mot de passe d'application (16 caractères)
define("SMTP_FROM_NAME", "Cave à Vins");


//---------------------------------------------------------------
// LIEN VERS LE SITE (utilisé dans le bouton de l'email)
//---------------------------------------------------------------

define("URL_SITE", "http://localhost/cave_a_vins/client/accueil_client.php");
