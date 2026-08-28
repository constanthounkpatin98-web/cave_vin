<?php

//===============================================
// FONCTIONS FIDÉLITÉ
// À inclure : require_once("../fidelite/fonctions_fidelite.php");
// Nécessite $connexion (PDO) déjà ouvert
//===============================================

//===============================================
// Attribue des points à un client après un paiement validé
// Règle : 1 point tous les 10 000 FCFA dépensés
//===============================================

function attribuer_points_fidelite($connexion, $id_client, $id_commande, $montant_total)
{
    $points = floor($montant_total / 10000);

    if($points <= 0)
    {
        return 0;
    }

    $connexion->prepare("
        UPDATE client
        SET points_fidelite = points_fidelite + ?
        WHERE id_client = ?
    ")->execute([$points, $id_client]);

    $connexion->prepare("
        INSERT INTO historique_fidelite (id_client, id_commande, points, type, description)
        VALUES (?, ?, ?, 'Gagné', ?)
    ")->execute([$id_client, $id_commande, $points, "Achat commande #" . $id_commande]);

    return $points;
}

//===============================================
// Vérifie si un client a assez de points pour acheter un vin donné
// (retourne true si le vin n'a aucune restriction)
//===============================================

function client_peut_acheter_vin($connexion, $id_client, $id_vin)
{
    $requete_vin = $connexion->prepare("SELECT points_fidelite_requis FROM vin WHERE id_vin = ?");
    $requete_vin->execute([$id_vin]);
    $vin = $requete_vin->fetch();

    if(!$vin || $vin["points_fidelite_requis"] <= 0)
    {
        return true;
    }

    return points_client($connexion, $id_client) >= $vin["points_fidelite_requis"];
}

//===============================================
// Retourne le solde de points d'un client
//===============================================

function points_client($connexion, $id_client)
{
    $requete = $connexion->prepare("SELECT points_fidelite FROM client WHERE id_client = ?");
    $requete->execute([$id_client]);
    $resultat = $requete->fetch();

    return $resultat ? (int)$resultat["points_fidelite"] : 0;
}

//===============================================
// Débite des points (utilisation contre une réduction)
//===============================================

function utiliser_points_fidelite($connexion, $id_client, $points, $description)
{
    if($points <= 0 || points_client($connexion, $id_client) < $points)
    {
        return false;
    }

    $connexion->prepare("
        UPDATE client
        SET points_fidelite = points_fidelite - ?
        WHERE id_client = ?
    ")->execute([$points, $id_client]);

    $connexion->prepare("
        INSERT INTO historique_fidelite (id_client, points, type, description)
        VALUES (?, ?, 'Utilisé', ?)
    ")->execute([$id_client, $points, $description]);

    return true;
}
