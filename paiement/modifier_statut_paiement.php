<?php

session_start();

//===============================================
// Sécurité : administrateur connecté
//===============================================

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}


//===============================================
// Statuts autorisés et transitions logiques permises
//===============================================
// clé   = statut actuel du paiement en base
// valeur = liste des statuts vers lesquels on a le droit de basculer

const STATUTS_AUTORISES = ["En attente", "Validé", "Échoué", "Remboursé"];

const TRANSITIONS_AUTORISEES = [
    "En attente" => ["Validé", "Échoué"],
    "Validé"     => ["Remboursé"],
    "Échoué"     => [],
    "Remboursé"  => [],
];


//===============================================
// 1) Récupération et validation des paramètres GET
//===============================================

$id_paiement       = filter_input(INPUT_GET, "id_paiement", FILTER_VALIDATE_INT);
$nouveau_statut    = $_GET["statut"] ?? null;

if(!$id_paiement || $id_paiement <= 0)
{
    header("Location: liste_paiement.php?erreur=" . urlencode("Identifiant de paiement invalide."));
    exit();
}

if(!$nouveau_statut || !in_array($nouveau_statut, STATUTS_AUTORISES, true))
{
    header("Location: liste_paiement.php?erreur=" . urlencode("Statut demandé invalide."));
    exit();
}


//===============================================
// 2) Vérifier que le paiement existe et récupérer son statut actuel
//===============================================

try
{
    $req_paiement = $connexion->prepare("
        SELECT id_paiement, statut
        FROM paiement
        WHERE id_paiement = ?
    ");
    $req_paiement->execute([$id_paiement]);
    $paiement = $req_paiement->fetch();

    if(!$paiement)
    {
        header("Location: liste_paiement.php?erreur=" . urlencode("Ce paiement n'existe pas."));
        exit();
    }

    $statut_actuel = trim((string) $paiement["statut"]);

    // Tolérance : si le statut stocké en base a un souci d'encodage
    // (ex: connexion PDO non configurée en UTF-8), on tente de le
    // reconvertir avant de le comparer à la liste des transitions.
    if($statut_actuel !== '' && !mb_check_encoding($statut_actuel, 'UTF-8'))
    {
        $converti = @mb_convert_encoding($statut_actuel, 'UTF-8', 'ISO-8859-1');
        if($converti !== false)
        {
            $statut_actuel = $converti;
        }
    }


    //===============================================
    // 3) Vérifier que la transition demandée est logique
    //===============================================

    $transitions_possibles = TRANSITIONS_AUTORISEES[$statut_actuel] ?? [];

    if(!in_array($nouveau_statut, $transitions_possibles, true))
    {
        header("Location: liste_paiement.php?erreur=" . urlencode(
            "Transition impossible : un paiement au statut « $statut_actuel » ne peut pas passer à « $nouveau_statut »."
        ));
        exit();
    }


    //===============================================
    // 4) Mise à jour du statut (requête préparée)
    //===============================================

    $req_maj = $connexion->prepare("
        UPDATE paiement
        SET statut = ?
        WHERE id_paiement = ?
    ");
    $req_maj->execute([$nouveau_statut, $id_paiement]);

    header("Location: liste_paiement.php?modifier=ok");
    exit();
}
catch(PDOException $e)
{
    header("Location: liste_paiement.php?erreur=" . urlencode("Erreur lors de la mise à jour du paiement."));
    exit();
}