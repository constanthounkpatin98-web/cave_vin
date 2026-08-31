<?php

//===============================================
// FONCTIONS FIDÉLITÉ — Cave à Vins
// Emplacement attendu : client/fonctions_fidelite.php
// Nécessite $connexion (PDO) déjà ouvert avant l'inclusion
//===============================================


//===============================================
// Garantit qu'un client possède bien une ligne dans `fidelite`
// (créée automatiquement au premier appel si elle n'existe pas)
//===============================================

function assurer_compte_fidelite($connexion, $id_client)
{
    $requete = $connexion->prepare("SELECT id_fidelite FROM fidelite WHERE id_client = ?");
    $requete->execute([$id_client]);

    if (!$requete->fetch()) {
        $connexion->prepare("
            INSERT INTO fidelite (id_client, points_actuels, total_depense, nombre_recompenses_disponibles)
            VALUES (?, 0, 0, 0)
        ")->execute([$id_client]);
    }
}


//===============================================
// Attribue les points de fidélité pour une commande réellement payée
//
// Règle : 5 points par tranche complète de 10 000 FCFA DÉPENSÉS AU CUMUL.
// Le calcul compare le palier atteint AVANT et APRÈS cette commande sur
// le total_depense du client, ce qui conserve automatiquement le "reste"
// d'une commande à l'autre (ex: 7 000 puis 5 000 => 0 pt puis 5 pts,
// reste 2 000 FCFA conservé dans total_depense).
//
// Protégé contre la double attribution (vérifie l'historique par id_commande).
// Retourne le nombre de points attribués (peut être 0 si le palier n'est
// pas encore atteint — le montant est quand même comptabilisé dans le cumul).
//===============================================

function attribuer_points_fidelite($connexion, $id_client, $id_commande, $montant_paye)
{
    //-----------------------------------------------
    // Déjà attribué pour cette commande ? On ne refait rien.
    //-----------------------------------------------

    $verif = $connexion->prepare("
        SELECT id_historique
        FROM historique_fidelite
        WHERE id_commande = ? AND type_operation = 'Gagné'
    ");
    $verif->execute([$id_commande]);

    if ($verif->fetch()) {
        return 0;
    }

    if ($montant_paye <= 0) {
        return 0;
    }

    $transaction_ouverte_ici = false;

    if (!$connexion->inTransaction()) {
        $connexion->beginTransaction();
        $transaction_ouverte_ici = true;
    }

    try {

        assurer_compte_fidelite($connexion, $id_client);

        //-----------------------------------------------
        // On verrouille la ligne et on lit le cumul AVANT cette commande
        //-----------------------------------------------

        $requete_solde = $connexion->prepare("
            SELECT points_actuels, total_depense
            FROM fidelite
            WHERE id_client = ?
            FOR UPDATE
        ");
        $requete_solde->execute([$id_client]);
        $ligne = $requete_solde->fetch();

        $solde_actuel          = (int) $ligne["points_actuels"];
        $ancien_total_depense  = (float) $ligne["total_depense"];
        $nouveau_total_depense = $ancien_total_depense + $montant_paye;

        //-----------------------------------------------
        // Points dus = différence de palier entre l'ancien et le nouveau
        // cumul. C'est ce qui permet de conserver le "reste" d'une
        // commande sur l'autre au lieu de recalculer commande par commande.
        //-----------------------------------------------

        $anciens_points_cumules  = (int) (floor($ancien_total_depense / 10000) * 5);
        $nouveaux_points_cumules = (int) (floor($nouveau_total_depense / 10000) * 5);

        $points = $nouveaux_points_cumules - $anciens_points_cumules;

        $nouveau_solde = $solde_actuel + $points;

        $connexion->prepare("
            UPDATE fidelite
            SET points_actuels = ?,
                total_depense = ?,
                nombre_recompenses_disponibles = FLOOR(? / 50)
            WHERE id_client = ?
        ")->execute([$nouveau_solde, $nouveau_total_depense, $nouveau_solde, $id_client]);

        //-----------------------------------------------
        // On trace TOUJOURS le passage de la commande (même à 0 point),
        // pour que la protection anti-double-attribution fonctionne
        // aussi pour les montants qui n'ont pas encore atteint un palier.
        //-----------------------------------------------

        $reste_actuel = $nouveau_total_depense % 10000;

        $description = $points > 0
            ? "Points gagnés pour la commande #" . $id_commande . " (reste " . $reste_actuel . " FCFA conservé)"
            : "Commande #" . $id_commande . " ajoutée au cumul, aucun nouveau palier atteint (reste " . $reste_actuel . " FCFA)";

        $connexion->prepare("
            INSERT INTO historique_fidelite (id_client, id_commande, type_operation, points, description)
            VALUES (?, ?, 'Gagné', ?, ?)
        ")->execute([$id_client, $id_commande, $points, $description]);

        if ($transaction_ouverte_ici) {
            $connexion->commit();
        }

    } catch (Exception $e) {

        if ($transaction_ouverte_ici && $connexion->inTransaction()) {
            $connexion->rollBack();
        }

        error_log("Erreur attribution points fidélité : " . $e->getMessage());
        return 0;
    }

    return $points;
}


//===============================================
// Retourne les infos fidélité d'un client (crée le compte si besoin)
//===============================================

function obtenir_fidelite_client($connexion, $id_client)
{
    assurer_compte_fidelite($connexion, $id_client);

    $requete = $connexion->prepare("SELECT * FROM fidelite WHERE id_client = ?");
    $requete->execute([$id_client]);

    return $requete->fetch();
}


//===============================================
// Liste des vins éligibles à une récompense (prix <= 10 000 FCFA,
// disponibles et en stock)
//===============================================

function vins_eligibles_recompense($connexion)
{
    $requete = $connexion->prepare("
        SELECT * FROM vin
        WHERE prix <= 10000
          AND statut = 'Disponible'
          AND quantite_stock > 0
        ORDER BY prix ASC
    ");
    $requete->execute();

    return $requete->fetchAll();
}


//===============================================
// Utilise une récompense (50 points -> 1 vin <= 10 000 FCFA)
// Toutes les vérifications sont refaites côté serveur, ne jamais
// faire confiance aux données envoyées par le navigateur.
//
// Retourne un tableau ["succes" => bool, "erreur" => string|null, "vin" => array|null]
//===============================================

function utiliser_recompense_fidelite($connexion, $id_client, $id_vin)
{
    $transaction_ouverte_ici = false;

    if (!$connexion->inTransaction()) {
        $connexion->beginTransaction();
        $transaction_ouverte_ici = true;
    }

    try {

        assurer_compte_fidelite($connexion, $id_client);

        //-----------------------------------------------
        // Vérifier le solde de points
        //-----------------------------------------------

        $requete_solde = $connexion->prepare("
            SELECT points_actuels FROM fidelite WHERE id_client = ? FOR UPDATE
        ");
        $requete_solde->execute([$id_client]);
        $solde_actuel = (int) $requete_solde->fetchColumn();

        if ($solde_actuel < 50) {
            if ($transaction_ouverte_ici) $connexion->rollBack();
            return ["succes" => false, "erreur" => "points_insuffisants", "vin" => null];
        }

        //-----------------------------------------------
        // Vérifier le vin (existence, disponibilité, stock, prix)
        //-----------------------------------------------

        $requete_vin = $connexion->prepare("SELECT * FROM vin WHERE id_vin = ? FOR UPDATE");
        $requete_vin->execute([$id_vin]);
        $vin = $requete_vin->fetch();

        if (!$vin) {
            if ($transaction_ouverte_ici) $connexion->rollBack();
            return ["succes" => false, "erreur" => "vin_introuvable", "vin" => null];
        }

        if ($vin["statut"] !== "Disponible") {
            if ($transaction_ouverte_ici) $connexion->rollBack();
            return ["succes" => false, "erreur" => "vin_indisponible", "vin" => null];
        }

        if ((int) $vin["quantite_stock"] <= 0) {
            if ($transaction_ouverte_ici) $connexion->rollBack();
            return ["succes" => false, "erreur" => "stock_epuise", "vin" => null];
        }

        if ((float) $vin["prix"] > 10000) {
            if ($transaction_ouverte_ici) $connexion->rollBack();
            return ["succes" => false, "erreur" => "vin_trop_cher", "vin" => null];
        }

        //-----------------------------------------------
        // Débiter les points
        //-----------------------------------------------

        $nouveau_solde = $solde_actuel - 50;

        $connexion->prepare("
            UPDATE fidelite
            SET points_actuels = ?,
                nombre_recompenses_disponibles = FLOOR(? / 50)
            WHERE id_client = ?
        ")->execute([$nouveau_solde, $nouveau_solde, $id_client]);

        //-----------------------------------------------
        // Diminuer le stock du vin de 1 + tracer le mouvement
        //-----------------------------------------------

        $nouveau_stock = (int) $vin["quantite_stock"] - 1;

        $connexion->prepare("
            UPDATE vin SET quantite_stock = ? WHERE id_vin = ?
        ")->execute([$nouveau_stock, $id_vin]);

        $connexion->prepare("
            INSERT INTO stock_mouvement (type_mouvement, quantite, stock_apres, id_vin, id_admin)
            VALUES ('Sortie', 1, ?, ?, NULL)
        ")->execute([$nouveau_stock, $id_vin]);

        //-----------------------------------------------
        // Historique + trace de la récompense
        //-----------------------------------------------

        $connexion->prepare("
            INSERT INTO historique_fidelite (id_client, id_commande, type_operation, points, description)
            VALUES (?, NULL, 'Utilisé', 50, ?)
        ")->execute([$id_client, "Récompense : " . $vin["nom_vin"]]);

        $id_historique = $connexion->lastInsertId();

        $connexion->prepare("
            INSERT INTO recompense_utilisee (id_client, id_vin, id_historique)
            VALUES (?, ?, ?)
        ")->execute([$id_client, $id_vin, $id_historique]);

        if ($transaction_ouverte_ici) {
            $connexion->commit();
        }

        return ["succes" => true, "erreur" => null, "vin" => $vin];

    } catch (Exception $e) {

        if ($transaction_ouverte_ici && $connexion->inTransaction()) {
            $connexion->rollBack();
        }

        error_log("Erreur utilisation récompense fidélité : " . $e->getMessage());
        return ["succes" => false, "erreur" => "erreur_serveur", "vin" => null];
    }
}
