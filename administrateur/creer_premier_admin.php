<?php

//===============================================
// SCRIPT À EXÉCUTER UNE SEULE FOIS
// Puis SUPPRIMER ce fichier pour des raisons de sécurité
//===============================================

require_once("../connexion.php");

$nom          = "Admin";
$prenom       = "Principal";
$email        = "constanthounkpatin98@gmaiil.com";
$mot_de_passe = "Cons@98"; // change ce mot de passe avant/après exécution
$role         = "Super Administrateur";

$mot_de_passe_hache = password_hash($mot_de_passe, PASSWORD_DEFAULT);

// Vérifier qu'il n'existe pas déjà
$verif = $connexion->prepare("SELECT id_admin FROM administrateur WHERE email = ?");
$verif->execute([$email]);

if($verif->fetch())
{
    echo "Un administrateur avec cet email existe déjà.";
    exit();
}

$requete = $connexion->prepare("

INSERT INTO administrateur (nom, prenom, email, mot_de_passe, role, statut)

VALUES (?, ?, ?, ?, ?, 'Actif')

");

$requete->execute([$nom, $prenom, $email, $mot_de_passe_hache, $role]);

echo "Administrateur créé avec succès !<br>";
echo "Email : " . $email . "<br>";
echo "Mot de passe : " . $mot_de_passe . "<br>";
echo "<strong>Supprime ce fichier maintenant.</strong>";

?>
