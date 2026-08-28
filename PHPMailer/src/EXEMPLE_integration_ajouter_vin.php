<?php

//===============================================================
// CECI EST UN EXEMPLE — pas un fichier à exécuter tel quel.
//
// Je n'ai pas ton fichier admin/ajouter_vin.php, donc voici
// exactement ce qu'il faut ajouter, et où, dans TON fichier.
//===============================================================


// ... tout ton code existant d'ajout de vin reste identique ...

// Exemple type de ce que tu as probablement déjà :
//
// $requete = $connexion->prepare("
//     INSERT INTO vin (nom_vin, prix, id_categorie, quantite_stock, ...)
//     VALUES (?, ?, ?, ?, ...)
// ");
// $requete->execute([$nom_vin, $prix, $id_categorie, $quantite_stock, ...]);


//===============================================================
// AJOUTE CE BLOC JUSTE APRÈS L'INSERTION RÉUSSIE DU VIN
// (donc après le $requete->execute([...]) ci-dessus)
//===============================================================

require_once __DIR__ . "/../notifications/notifier_nouveau_vin.php";

$vin_ajoute = [
    "id_vin"  => $connexion->lastInsertId(),
    "nom_vin" => $nom_vin,   // remplace par le nom exact de TA variable
    "prix"    => $prix,      // remplace par le nom exact de TA variable
];

notifierClientsNouveauVin($vin_ajoute);

// Puis continue normalement (redirection vers la liste des vins, etc.)
