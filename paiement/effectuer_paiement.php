<?php

session_start();

require_once("../connexion.php");

//===============================================
// Sécurité : client connecté
//===============================================

if(!isset($_SESSION["client_id"]))
{
    header("Location: ../client/connexion_client.php");
    exit();
}

//===============================================
// Récupération de l'id_commande (POST prioritaire, GET en secours)
//===============================================

$id_commande = $_POST["id_commande"] ?? $_GET["id_commande"] ?? null;

if(!$id_commande)
{
    header("Location: ../client/accueil_client.php");
    exit();
}

$id_commande = (int)$id_commande;
$id_client   = $_SESSION["client_id"];

//===============================================
// Vérifier que la commande appartient bien au client
//===============================================

$requete = $connexion->prepare("SELECT * FROM commande WHERE id_commande = ? AND id_client = ?");
$requete->execute([$id_commande, $id_client]);
$commande = $requete->fetch();

if(!$commande)
{
    header("Location: ../client/accueil_client.php");
    exit();
}

//===============================================
// Vérifier qu'aucun paiement n'existe déjà pour cette commande
//===============================================

$requete_paiement = $connexion->prepare("SELECT * FROM paiement WHERE id_commande = ?");
$requete_paiement->execute([$id_commande]);
$paiement_existant = $requete_paiement->fetch();

if($paiement_existant)
{
    if($paiement_existant["statut"] === "Validé")
    {
        header("Location: confirmation_commande.php?id_commande=".$id_commande);
    }
    else
    {
        header("Location: paiement_en_cours.php?id_commande=".$id_commande);
    }
    exit();
}

//===============================================
// Cette page ne fait que TRAITER le formulaire envoyé par paiement.php
// Un accès direct (GET) renvoie vers la page de sélection du moyen de paiement
//===============================================

if($_SERVER["REQUEST_METHOD"] !== "POST")
{
    header("Location: paiement.php?id_commande=".$id_commande);
    exit();
}

//===============================================
// Correspondance entre les id des méthodes (paiement.php) et leur libellé
//===============================================

$libelles_methodes = [
    "mtn"       => "MTN Mobile Money",
    "moov"      => "Moov Money",
    "celtiis"   => "Celtiis Money",
    "carte"     => "Carte bancaire",
    "livraison" => "Paiement à la livraison",
    "virement"  => "Virement bancaire",
];

$methode = $_POST["methode"] ?? "";

if(empty($methode) || !isset($libelles_methodes[$methode]))
{
    header("Location: paiement.php?id_commande=".$id_commande."&erreur=1");
    exit();
}

//===============================================
// Validation selon le type de moyen de paiement choisi
//===============================================

if(in_array($methode, ["mtn", "moov", "celtiis"]))
{
    $numero_mobile = trim($_POST["numero_mobile"] ?? "");

    if(empty($numero_mobile))
    {
        header("Location: paiement.php?id_commande=".$id_commande."&erreur=numero");
        exit();
    }
}

if($methode === "carte")
{
    $numero_carte    = trim($_POST["numero_carte"] ?? "");
    $expiration_carte = trim($_POST["expiration_carte"] ?? "");
    $cvv_carte       = trim($_POST["cvv_carte"] ?? "");
    $titulaire_carte = trim($_POST["titulaire_carte"] ?? "");

    if(empty($numero_carte) || empty($expiration_carte) || empty($cvv_carte) || empty($titulaire_carte))
    {
        header("Location: paiement.php?id_commande=".$id_commande."&erreur=carte");
        exit();
    }
}

//===============================================
// Enregistrement du paiement
//===============================================

$mode_paiement = $libelles_methodes[$methode];
$reference     = strtoupper(uniqid("PAY-"));

$requete_insertion = $connexion->prepare("
    INSERT INTO paiement (date_paiement, mode_paiement, montant, statut, reference_transaction, id_commande)
    VALUES (NOW(), ?, ?, 'En attente', ?, ?)
");

$requete_insertion->execute([
    $mode_paiement,
    $commande["montant_total"],
    $reference,
    $id_commande,
]);

header("Location: paiement_en_cours.php?id_commande=".$id_commande);
exit();