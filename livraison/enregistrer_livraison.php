<?php

session_start();

require_once("../connexion.php");

//===============================================
// Sécurité : client connecté
//===============================================

if (!isset($_SESSION["client_id"])) {
    header("Location: ../client/connexion_client.php");
    exit();
}


//===============================================
// Le panier ne doit pas être vide
//===============================================

if (!isset($_SESSION["panier"]) || count($_SESSION["panier"]) === 0) {
    header("Location: ../panier/panier.php");
    exit();
}


//===============================================
// Vérification de la méthode et des champs
//===============================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../livraison/formulaire_livraison.php");
    exit();
}

$adresse_livraison = isset($_POST["adresse_livraison"]) ? trim($_POST["adresse_livraison"]) : "";
$mode_livraison     = isset($_POST["mode_livraison"]) ? trim($_POST["mode_livraison"]) : "";

$frais_par_mode = [
    "Standard" => 1500,
    "Express"  => 3500,
];

$erreurs = [];

if ($adresse_livraison === "") {
    $erreurs[] = "L'adresse de livraison est obligatoire.";
}

if (!array_key_exists($mode_livraison, $frais_par_mode)) {
    $erreurs[] = "Veuillez choisir un mode de livraison valide.";
}

if (!empty($erreurs)) {
    $_SESSION["erreurs_livraison"] = $erreurs;
    $_SESSION["valeurs_livraison"] = [
        "adresse_livraison" => $adresse_livraison,
        "mode_livraison"    => $mode_livraison,
    ];
    header("Location: ../livraison/formulaire_livraison.php");
    exit();
}

$frais_livraison = $frais_par_mode[$mode_livraison];


//===============================================
// Calcul du montant total des articles du panier
//===============================================

$ids = array_keys($_SESSION["panier"]);
$in  = str_repeat("?,", count($ids) - 1) . "?";

$requete_vins = $connexion->prepare("SELECT id_vin, prix FROM vin WHERE id_vin IN ($in)");
$requete_vins->execute($ids);

$montant_articles = 0;
$articles_panier  = [];

while ($vin = $requete_vins->fetch()) {

    $quantite   = $_SESSION["panier"][$vin["id_vin"]];
    $sous_total = $vin["prix"] * $quantite;

    $articles_panier[] = [
        "id_vin"    => $vin["id_vin"],
        "prix"      => $vin["prix"],
        "quantite"  => $quantite,
        "sous_total" => $sous_total,
    ];

    $montant_articles += $sous_total;

}

$montant_total = $montant_articles + $frais_livraison;


//===============================================
// Création de la commande + de la livraison
//===============================================

try {

    $connexion->beginTransaction();

    // 1. Création de la commande

    $requete_commande = $connexion->prepare("

        INSERT INTO commande (
            date_commande,
            montant_total,
            statut,
            mode_livraison,
            id_client
        ) VALUES (
            NOW(),
            ?,
            'En attente',
            ?,
            ?
        )

    ");

    $requete_commande->execute([
        $montant_total,
        $mode_livraison,
        $_SESSION["client_id"],
    ]);

    $id_commande = $connexion->lastInsertId();


    // 2. Création de la livraison associée

    $requete_livraison = $connexion->prepare("

        INSERT INTO livraison (
            adresse_livraison,
            date_livraison,
            statut,
            frais_livraison,
            num_suivi,
            id_commande
        ) VALUES (
            ?,
            NULL,
            'En attente',
            ?,
            NULL,
            ?
        )

    ");

    $requete_livraison->execute([
        $adresse_livraison,
        $frais_livraison,
        $id_commande,
    ]);


    // 3. Création des lignes de commande (détail des vins)

    $requete_ligne = $connexion->prepare("

        INSERT INTO ligne_commande (
            quantite,
            prix_unitaire,
            sous_total,
            id_commande,
            id_vin
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?
        )

    ");

    foreach ($articles_panier as $article) {

        $requete_ligne->execute([
            $article["quantite"],
            $article["prix"],
            $article["sous_total"],
            $id_commande,
            $article["id_vin"],
        ]);

    }

    $connexion->commit();

} catch (Exception $e) {

    $connexion->rollBack();

    $_SESSION["erreurs_livraison"] = [
        "Une erreur est survenue lors de la création de votre commande. Veuillez réessayer.",
    ];

    header("Location: ../livraison/formulaire_livraison.php");
    exit();

}


//===============================================
// Redirection vers la confirmation de commande
// (le paiement se fait depuis cette page)
//===============================================

header("Location: ../livraison/confirmation_commande.php?id_commande=" . $id_commande);
exit();

?>
