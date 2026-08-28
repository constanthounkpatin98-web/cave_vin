<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["panier"]))
{
    $_SESSION["panier"] = [];
}

//===============================================
// Vider le panier (nouvelle action, gérée ici pour
// ne pas créer de fichier supplémentaire)
//===============================================

if(isset($_POST["action"]) && $_POST["action"] == "vider")
{
    $_SESSION["panier"] = [];
    header("Location: panier.php");
    exit();
}

//===============================================
// Récupération des vins présents dans le panier
//===============================================

$articles      = [];
$montant_total = 0;
$nb_articles   = 0;

if(count($_SESSION["panier"]) > 0)
{
    $ids  = array_keys($_SESSION["panier"]);
    $in   = str_repeat("?,", count($ids) - 1) . "?";

    $requete = $connexion->prepare("SELECT * FROM vin WHERE id_vin IN ($in)");
    $requete->execute($ids);

    while($vin = $requete->fetch())
    {
        $quantite   = $_SESSION["panier"][$vin["id_vin"]];
        $sous_total = $vin["prix"] * $quantite;

        $articles[] = [
            "vin"        => $vin,
            "quantite"   => $quantite,
            "sous_total" => $sous_total,
        ];

        $montant_total += $sous_total;
        $nb_articles   += $quantite;
    }
}

//===============================================
// Suggestions : quelques vins disponibles qui ne
// sont pas déjà dans le panier
//===============================================

$suggestions = [];

$ids_panier = array_keys($_SESSION["panier"]);

if(count($ids_panier) > 0)
{
    $in_exclu = str_repeat("?,", count($ids_panier) - 1) . "?";
    $requete_sugg = $connexion->prepare("
        SELECT * FROM vin
        WHERE statut = 'Disponible' AND quantite_stock > 0 AND id_vin NOT IN ($in_exclu)
        ORDER BY RAND() LIMIT 6
    ");
    $requete_sugg->execute($ids_panier);
}
else
{
    $requete_sugg = $connexion->prepare("
        SELECT * FROM vin
        WHERE statut = 'Disponible' AND quantite_stock > 0
        ORDER BY RAND() LIMIT 6
    ");
    $requete_sugg->execute();
}

$suggestions = $requete_sugg->fetchAll();


//===============================================
// Informations d'entête (panier / notifications / commandes)
//===============================================

$nombre_panier         = $nb_articles;
$nombre_notifications  = 0;
$nombre_commandes_client = 0;

if(isset($_SESSION["client_id"]))
{
    $requete_notif = $connexion->prepare("

        SELECT COUNT(*) AS total
        FROM notification
        WHERE id_client = ? AND statut = 'Non lue'

    ");

    $requete_notif->execute([$_SESSION["client_id"]]);

    $nombre_notifications = $requete_notif->fetch()["total"];


    $requete_commandes = $connexion->prepare("

        SELECT COUNT(*) AS total
        FROM commande
        WHERE id_client = ?

    ");

    $requete_commandes->execute([$_SESSION["client_id"]]);

    $nombre_commandes_client = $requete_commandes->fetch()["total"];
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Mon Panier</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
<link
        rel="stylesheet"
        href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css"
    >


<style>

    /* =====================================================
       POLICES & VARIABLES
    ===================================================== */

    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Poppins:wght@400;500;600;700&display=swap');

    :root {

        --noir:      #1c1a19;
        --anthracite:#2b2725;
        --bordeaux:  #6d1626;
        --bordeaux-fonce: #4e0f1c;
        --or:        #c9a24b;
        --creme:     #f6f1ea;

    }


    body {

        background: var(--creme);
        font-family: 'Poppins', sans-serif;
        color: #2c2622;

    }


    h1, h2, h3, h4, h5, h6 {

        font-family: 'Playfair Display', serif;

    }


    a {

        text-decoration: none;

    }


    /* =====================================================
       BARRE SUPERIEURE
    ===================================================== */

    .barre-haut {

        background: var(--noir);
        color: #cfc8c2;
        font-size: 0.78rem;
        padding: 6px 0;
        letter-spacing: .04em;

    }


    /* =====================================================
       ENTETE
    ===================================================== */

    .entete {

        background: var(--anthracite);
        color: #fff;
        padding: 18px 0;
        border-bottom: 3px solid var(--bordeaux);

    }


    .entete a {

        color: #ece7e1;
        text-decoration: none;
        transition: color .15s ease;

    }


    .entete a:hover {

        color: var(--or);

    }


    .logo-cave {

        font-family: 'Playfair Display', serif;
        font-weight: 700;
        font-size: 1.5rem;
        letter-spacing: .03em;
        color: #fff !important;

    }


    .logo-cave span {

        color: var(--or);

    }


    .nom-client {

        color: #cfc8c2;
        font-size: 0.9rem;

    }


    .badge-panier {

        background: var(--bordeaux);
        color: #fff;
        border-radius: 50%;
        padding: 2px 7px;
        font-size: 0.75rem;
        position: relative;
        top: -10px;
        left: -8px;

    }


    /* =====================================================
       FIL D'ARIANE
    ===================================================== */

    .fil-ariane {

        background: var(--noir);
        color: #cfc8c2;
        padding: 14px 0;
        font-size: .85rem;

    }


    .fil-ariane a {

        color: #cfc8c2;

    }


    .fil-ariane a:hover {

        color: var(--or);

    }


    .fil-ariane span.actif {

        color: var(--or);

    }


    /* =====================================================
       BLOC PANIER
    ===================================================== */

    .carte-panier {

        border: 1px solid #efe8de;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);
        background: #fff;

    }


    .icone-titre {

        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: var(--bordeaux);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;

    }


    .ligne-article {

        border-bottom: 1px solid #efe8de;

    }


    .vignette-article {

        width: 68px;
        height: 68px;
        border-radius: 10px;
        background: var(--creme);
        overflow: hidden;
        flex-shrink: 0;
        border: 1px solid #efe8de;
        padding: 6px;
        box-sizing: border-box;

    }


    .vignette-article img {

        width: 100%;
        height: 100%;
        object-fit: contain;

    }


    .nom-article {

        font-family: 'Playfair Display', serif;
        font-weight: 700;

    }


    .badge-stock-cave {

        background: #e9f4ec;
        color: #1f7a45;
        font-weight: 600;

    }


    .sous-total-article {

        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: var(--bordeaux);

    }


    .btn-bordeaux {

        background: var(--bordeaux);
        border-color: var(--bordeaux);
        color: #fff;
        font-weight: 600;

    }


    .btn-bordeaux:hover {

        background: var(--bordeaux-fonce);
        border-color: var(--bordeaux-fonce);
        color: #fff;

    }


    .btn-outline-noir {

        border: 1px solid #2c2622;
        color: #2c2622;
        background: transparent;
        font-weight: 600;

    }


    .btn-outline-noir:hover {

        background: #2c2622;
        color: #fff;

    }


    .btn-outline-bordeaux-sm {

        border: 1px solid #e6c9ce;
        color: var(--bordeaux);
        background: #fdf5f6;

    }


    .btn-outline-bordeaux-sm:hover {

        background: var(--bordeaux);
        color: #fff;
        border-color: var(--bordeaux);

    }


    /* =====================================================
       SUGGESTIONS
    ===================================================== */

    .titre-section {

        display: flex;
        align-items: center;
        gap: 14px;
        margin: 40px 0 22px;

    }


    .titre-section h2 {

        font-weight: 700;
        font-size: 1.3rem;
        margin: 0;
        white-space: nowrap;

    }


    .titre-section .trait {

        flex: 1;
        height: 1px;
        background: linear-gradient(to right, #ddd2c2, transparent);

    }


    .carte-suggestion {

        border: 1px solid #efe8de;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        transition: transform .15s ease, box-shadow .15s ease;
        height: 100%;

    }


    .carte-suggestion:hover {

        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(28,20,20,.1);

    }


    .image-suggestion {

        aspect-ratio: 1/1;
        background: var(--creme);
        overflow: hidden;
        padding: 10px;
        box-sizing: border-box;

    }


    .image-suggestion img {

        width: 100%;
        height: 100%;
        object-fit: contain;

    }


    .btn-ajout-rond {

        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--bordeaux);
        border: none;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;

    }


    .btn-ajout-rond:hover {

        background: var(--bordeaux-fonce);

    }


    /* =====================================================
       RECAPITULATIF
    ===================================================== */

    .carte-recap {

        border: 1px solid #efe8de;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);

    }


    .carte-recap h5 {

        font-weight: 700;

    }


    .total-recap {

        color: var(--bordeaux);
        font-family: 'Playfair Display', serif;

    }


    .carte-engagements {

        border: 1px solid #efe8de;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);

    }


    .icone-engagement {

        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--creme);
        color: var(--bordeaux);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;

    }


    /* =====================================================
       PIED DE PAGE
    ===================================================== */

    .pied-cave {

        background: var(--noir);
        color: #beb4ab;
        padding: 44px 0 22px;
        font-size: .9rem;
        margin-top: 56px;

    }


    .pied-cave h6 {

        color: #fff;
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        letter-spacing: .03em;
        margin-bottom: 16px;

    }


    .pied-cave a {

        color: #beb4ab;

    }


    .pied-cave a:hover {

        color: var(--or);

    }


    .reseaux-sociaux a {

        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid #4a423c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;

    }


    .reseaux-sociaux a:hover {

        background: var(--bordeaux);
        border-color: var(--bordeaux);
        color: #fff;

    }


    .pied-cave .newsletter input {

        border-radius: 6px 0 0 6px;
        border: none;
        padding: 10px 12px;

    }


    .pied-cave .newsletter button {

        border-radius: 0 6px 6px 0;
        background: var(--or);
        border: none;
        color: var(--noir);
        font-weight: 700;
        padding: 0 16px;

    }


    .copyright-cave {

        border-top: 1px solid #3a332e;
        margin-top: 30px;
        padding-top: 18px;
        font-size: .78rem;
        color: #8a8078;

    }


    /* =====================================================
       RESPONSIVE : évite tout débordement horizontal
    ===================================================== */

    .container {
        width: 100%;
        max-width: 1320px;
    }

    .barre-haut,
    .entete,
    .fil-ariane,
    .pied-cave {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .ligne-article {
        min-width: 0;
    }

    .ligne-article .flex-grow-1 {
        min-width: 0;
    }

    .nom-article,
    .titre-section h2 {
        overflow-wrap: anywhere;
    }


    /* =====================================================
       TABLETTE : jusqu'à 991px
    ===================================================== */

    @media (max-width: 991.98px) {

        .carte-recap.sticky-top {
            position: static;
            top: auto;
        }
    }


    /* =====================================================
       MOBILE : jusqu'à 767px (incl. Samsung Galaxy S20/S21/S22...)
    ===================================================== */

    @media (max-width: 767.98px) {

        .container {
            padding-left: 12px;
            padding-right: 12px;
        }

        /* Barre supérieure : une seule info visible */
        .barre-haut {
            padding: 5px 0;
            font-size: .68rem;
        }

        .barre-haut .container {
            justify-content: center !important;
        }

        .barre-haut .container span:nth-child(2) {
            display: none;
        }

        /* En-tête compacte */
        .entete {
            padding: 12px 0;
        }

        .entete .container {
            gap: 10px !important;
        }

        .logo-cave {
            font-size: 1.05rem;
        }

        /* Fil d'Ariane */
        .fil-ariane {
            padding: 10px 0;
            font-size: .78rem;
        }

        /* Contenu */
        .container.py-4.py-md-5 {
            padding-top: 20px !important;
            padding-bottom: 20px !important;
        }

        .icone-titre {
            width: 40px;
            height: 40px;
        }

        h1.h3 {
            font-size: 1.25rem;
        }

        /* Carte panier */
        .carte-panier > .p-4 {
            padding: 16px !important;
        }

        /* Ligne article : passe en 2 lignes proprement */
        .ligne-article {
            flex-wrap: wrap;
            row-gap: 12px;
            gap: 12px !important;
        }

        .vignette-article {
            width: 56px;
            height: 56px;
        }

        .ligne-article .flex-grow-1 {
            flex: 1 1 calc(100% - 56px - 12px);
        }

        .nom-article {
            font-size: .95rem;
        }

        .controles-article {
            flex: 1 1 100%;
            justify-content: space-between;
            margin-left: calc(56px + 12px);
            width: calc(100% - 56px - 12px);
            gap: 10px !important;
        }

        .controles-article .input-group {
            width: 100px !important;
        }

        .controles-article .sous-total-article {
            min-width: 0 !important;
            font-size: .88rem;
        }

        /* Suggestions */
        .titre-section {
            margin: 30px 0 16px;
        }

        .titre-section h2 {
            font-size: 1.1rem;
            white-space: normal;
        }

        /* Récapitulatif */
        .carte-recap > .p-4,
        .carte-engagements > .p-4 {
            padding: 18px !important;
        }

        /* Footer : une colonne, plus d'air */
        .pied-cave {
            padding: 40px 0 24px;
            margin-top: 36px !important;
        }

        .pied-cave .row {
            --bs-gutter-y: 1.8rem;
        }

        .pied-cave [class*="col-"] {
            width: 100%;
        }

        .pied-cave h6 {
            font-size: 1.05rem;
        }

        .pied-cave .newsletter input {
            min-width: 0;
            flex: 1 1 auto;
        }

        .copyright-cave {
            font-size: .72rem;
            line-height: 1.6;
        }
    }


    /* =====================================================
       TRÈS PETITS ÉCRANS : jusqu'à 380px
    ===================================================== */

    @media (max-width: 380px) {

        .container {
            padding-left: 9px;
            padding-right: 9px;
        }

        .vignette-article {
            width: 48px;
            height: 48px;
        }

        .ligne-article .flex-grow-1 {
            flex-basis: calc(100% - 48px - 12px);
        }

        .controles-article {
            margin-left: calc(48px + 12px);
            width: calc(100% - 48px - 12px);
        }

        .controles-article .input-group {
            width: 88px !important;
        }

        .nom-article {
            font-size: .88rem;
        }

        .controles-article .sous-total-article {
            font-size: .8rem;
        }
    }


    /* =====================================================
       SIDEBAR COMPTE (mobile)
    ===================================================== */

    .btn-toggle-sidebar {

        background: transparent;
        border: none;
        color: #fff;
        padding: 2px 4px;
        line-height: 1;

    }


    .sidebar-compte {

        width: 280px;

    }


    .sidebar-compte .offcanvas-header {

        background: var(--noir);
        color: #fff;
        border-bottom: 3px solid var(--bordeaux);

    }


    .sidebar-compte .offcanvas-title {

        font-family: 'Playfair Display', serif;
        font-size: 1.05rem;

    }


    .sidebar-compte .offcanvas-body {

        background: var(--creme);
        padding: 18px;

    }


    .lien-sidebar {

        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 14px;
        border-radius: 8px;
        color: #2c2622;
        font-weight: 600;
        background: #fff;
        border: 1px solid #efe8de;

    }


    .lien-sidebar:hover {

        background: var(--bordeaux);
        color: #fff;
        border-color: var(--bordeaux);

    }


    .lien-sidebar i {

        font-size: 1.1rem;
        width: 22px;
        text-align: center;

    }


    .lien-sidebar .badge-panier {

        position: static;
        margin-left: auto;

    }

</style>

</head>

<body>


<!-- =====================================================
     BARRE SUPERIEURE
===================================================== -->

<div class="barre-haut">

    <div class="container d-flex justify-content-between align-items-center">

        <span>
            <i class="bi bi-truck"></i>
            Livraison rapide partout au Bénin
        </span>

        <span>
            <i class="bi bi-telephone"></i>
            Assistance : utilisez notre chatbot 🍷
        </span>

    </div>

</div>


<!-- =====================================================
     ENTETE
===================================================== -->

<div class="entete">

    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">

        <a href="../client/accueil_client.php" class="logo-cave">
            🍷 CAVE <span>À VINS</span>
        </a>


        <div class="d-none d-lg-flex align-items-center gap-4">

            <?php if(isset($_SESSION["client_id"])): ?>

                <span class="nom-client">
                    Bonjour,
                    <strong class="text-white">
                    <?php
                    echo htmlspecialchars(
                        $_SESSION["client_nom"] ?? ""
                    );
                    ?>
                    </strong>
                </span>


                <a href="../avis/donner_avis.php">
                    <i class="bi bi-star"></i>
                    Avis
                </a>


                <?php if ($nombre_commandes_client > 0): ?>

                    <a href="../client/mes_commandes.php">
                        <i class="bi bi-receipt"></i>
                        Mes commandes
                    </a>

                <?php endif; ?>


                <a href="../client/mes_notifications.php" class="position-relative">

                    <i class="bi bi-bell fs-5"></i>

                    <?php if ($nombre_notifications > 0): ?>

                        <span class="badge-panier">
                            <?php echo $nombre_notifications; ?>
                        </span>

                    <?php endif; ?>

                </a>

            <?php endif; ?>


            <a href="panier.php" class="position-relative">

                <i class="bi bi-cart3 fs-5"></i>

                <?php if ($nombre_panier > 0): ?>

                    <span class="badge-panier">
                        <?php echo $nombre_panier; ?>
                    </span>

                <?php endif; ?>

            </a>


            <?php if(isset($_SESSION["client_id"])): ?>

                <a href="../client/deconnexion_client.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Déconnexion
                </a>

            <?php else: ?>

                <a href="../client/connexion_client.php">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Connexion
                </a>

            <?php endif; ?>

        </div>


        <!-- Barre mobile : uniquement panier + bouton sidebar -->
        <div class="d-flex d-lg-none align-items-center gap-3">

            <a href="panier.php" class="position-relative text-white">

                <i class="bi bi-cart3 fs-5"></i>

                <?php if ($nombre_panier > 0): ?>

                    <span class="badge-panier">
                        <?php echo $nombre_panier; ?>
                    </span>

                <?php endif; ?>

            </a>

            <button class="btn-toggle-sidebar" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarCompte" aria-controls="sidebarCompte">
                <i class="bi bi-list fs-3"></i>
            </button>

        </div>

    </div>

</div>


<!-- =====================================================
     SIDEBAR COMPTE (mobile)
===================================================== -->

<div class="offcanvas offcanvas-end sidebar-compte" tabindex="-1" id="sidebarCompte" aria-labelledby="sidebarCompteLabel">

    <div class="offcanvas-header">

        <h5 class="offcanvas-title" id="sidebarCompteLabel">
            <?php if(isset($_SESSION["client_id"])): ?>
                Bonjour, <?php echo htmlspecialchars($_SESSION["client_nom"] ?? ""); ?>
            <?php else: ?>
                Mon compte
            <?php endif; ?>
        </h5>

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>

    </div>

    <div class="offcanvas-body d-flex flex-column gap-2">

        <?php if(isset($_SESSION["client_id"])): ?>

            <a href="../avis/donner_avis.php" class="lien-sidebar">
                <i class="bi bi-star"></i> Avis
            </a>

            <?php if ($nombre_commandes_client > 0): ?>
                <a href="../client/mes_commandes.php" class="lien-sidebar">
                    <i class="bi bi-receipt"></i> Mes commandes
                </a>
            <?php endif; ?>

            <a href="../client/mes_notifications.php" class="lien-sidebar">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($nombre_notifications > 0): ?>
                    <span class="badge-panier"><?php echo $nombre_notifications; ?></span>
                <?php endif; ?>
            </a>

            <a href="../client/deconnexion_client.php" class="lien-sidebar">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>

        <?php else: ?>

            <a href="../client/connexion_client.php" class="lien-sidebar">
                <i class="bi bi-box-arrow-in-right"></i> Connexion
            </a>

        <?php endif; ?>

    </div>

</div>

<div class="fil-ariane">

    <div class="container">

        <a href="../client/accueil_client.php">Accueil</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <span class="actif">Panier</span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container py-4 py-md-5">


<!-- ENTETE PAGE -->

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">

    <div class="d-flex align-items-start gap-3">

        <div class="icone-titre">
            <i class="bi bi-cart3 fs-5"></i>
        </div>

        <div>
            <h1 class="h3 fw-bold mb-1">Panier</h1>
            <p class="text-muted mb-0">Vérifiez vos articles avant de passer la commande.</p>
        </div>

    </div>

    <a href="../client/accueil_client.php" class="btn btn-outline-noir">
        <i class="bi bi-arrow-left"></i>
        Continuer mes achats
    </a>

</div>


<?php if(isset($_GET["erreur"]) && $_GET["erreur"] == "indisponible"): ?>

<div class="alert alert-danger">Ce vin n'est plus disponible en quantité suffisante.</div>

<?php elseif(isset($_GET["erreur"])): ?>

<div class="alert alert-danger"><?php echo htmlspecialchars($_GET["erreur"]); ?></div>

<?php endif; ?>


<?php if(count($articles) == 0): ?>

<div class="carte-panier text-center py-5">

    <div class="p-4">

        <div class="fs-1 mb-3">🍷</div>

        <p class="text-muted mb-3">Votre panier est vide.</p>

        <a href="../client/accueil_client.php" class="btn btn-bordeaux">Voir les vins</a>

    </div>

</div>

<?php else: ?>

<div class="row g-4">

<div class="col-lg-8">

<div class="carte-panier">

<div class="p-4">

<?php foreach($articles as $article):

    $vin   = $article["vin"];
    $photo = !empty($vin["photo"]) ? "../vin/uploads/".$vin["photo"] : null;

?>

<div class="d-flex align-items-center gap-3 py-3 ligne-article">

<div class="vignette-article d-flex align-items-center justify-content-center">

<?php if($photo): ?>

<img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($vin["nom_vin"]); ?>">

<?php else: ?>

<span class="fs-4">🍷</span>

<?php endif; ?>

</div>

<div class="flex-grow-1">

<div class="nom-article">
    <?php echo htmlspecialchars($vin["nom_vin"]); ?>
    <?php if(!empty($vin["millesime"])): ?>
        <span class="fw-normal">(<?php echo htmlspecialchars($vin["millesime"]); ?>)</span>
    <?php endif; ?>
</div>

<div class="text-muted small"><?php echo number_format($vin["prix"], 0, ',', ' '); ?> FCFA l'unité</div>

<span class="badge badge-stock-cave">
    <i class="bi bi-check-circle"></i>
    En stock
</span>

</div>

<form action="modifier_panier.php" method="POST" class="controles-article d-flex align-items-center gap-3">

<input type="hidden" name="id_vin" value="<?php echo $vin["id_vin"]; ?>">

<div class="input-group input-group-sm" style="width:120px;">

<button type="button" class="btn btn-outline-noir" onclick="stepQty(this,-1)">−</button>

<input type="number" name="quantite" class="form-control text-center" value="<?php echo $article["quantite"]; ?>" min="1" max="<?php echo $vin["quantite_stock"]; ?>" onchange="this.form.submit()">

<button type="button" class="btn btn-outline-noir" onclick="stepQty(this,1)">+</button>

</div>

<div class="sous-total-article text-end" style="min-width:100px;"><?php echo number_format($article["sous_total"], 0, ',', ' '); ?> FCFA</div>

<a href="supprimer_panier.php?id_vin=<?php echo $vin["id_vin"]; ?>" class="btn btn-outline-danger btn-sm" title="Retirer">

<i class="bi bi-trash"></i>

</a>

</form>

</div>

<?php endforeach; ?>

<form action="panier.php" method="POST" class="mt-3">

<input type="hidden" name="action" value="vider">

<button type="submit" class="btn btn-link text-danger text-decoration-none p-0 small" onclick="return confirm('Vider tout le panier ?');">

<i class="bi bi-trash3"></i>
Vider le panier

</button>

</form>

</div>

</div>


<?php if(count($suggestions) > 0): ?>

<div class="titre-section">
    <h2>Vous aimerez peut-être</h2>
    <div class="trait"></div>
</div>

<div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">

<?php foreach($suggestions as $sugg): $sphoto = !empty($sugg["photo"]) ? "../vin/uploads/".$sugg["photo"] : null; ?>

<div class="col">

<div class="carte-suggestion">

<div class="p-2 d-flex flex-column h-100">

<div class="image-suggestion rounded-3 d-flex align-items-center justify-content-center mb-2">

<?php if($sphoto): ?>

<img src="<?php echo htmlspecialchars($sphoto); ?>" alt="<?php echo htmlspecialchars($sugg["nom_vin"]); ?>">

<?php else: ?>

<span class="fs-3">🍷</span>

<?php endif; ?>

</div>

<div class="small fw-semibold" style="min-height:2.3em;"><?php echo htmlspecialchars($sugg["nom_vin"]); ?></div>

<div class="d-flex align-items-center justify-content-between mt-2">

<span class="small" style="color:var(--bordeaux); font-weight:700;"><?php echo number_format($sugg["prix"], 0, ',', ' '); ?> FCFA</span>

<form action="ajouter_panier.php" method="POST">

<input type="hidden" name="id_vin" value="<?php echo $sugg["id_vin"]; ?>">

<input type="hidden" name="quantite" value="1">

<button type="submit" class="btn-ajout-rond" title="Ajouter au panier">

<i class="bi bi-plus"></i>

</button>

</form>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>


<div class="col-lg-4">

<div class="carte-recap mb-3 sticky-top" style="top:1.5rem;">

<div class="p-4">

<h5 class="mb-3">Récapitulatif</h5>

<div class="d-flex justify-content-between small py-1">

<span class="text-muted">Sous-total (<?php echo $nb_articles; ?> article<?php echo $nb_articles > 1 ? "s" : ""; ?>)</span>

<span><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</span>

</div>

<div class="d-flex justify-content-between small py-1">

<span class="text-muted">Livraison</span>

<span class="text-muted">Calculée à l'étape suivante</span>

</div>

<div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2">

<span>Total</span>

<span class="total-recap"><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</span>

</div>

<a href="valider_panier.php" class="btn btn-bordeaux w-100 mt-3">
    Passer la commande
    <i class="bi bi-arrow-right"></i>
</a>

</div>

</div>


<div class="carte-engagements">

<div class="p-4">

<h6 class="mb-3">Nos engagements</h6>

<div class="list-group list-group-flush">

<div class="list-group-item d-flex gap-3 align-items-start px-0" style="background:transparent;">

<div class="icone-engagement">
<i class="bi bi-truck"></i>
</div>

<div>
<div class="fw-semibold small">Livraison rapide</div>
<div class="text-muted small">Livraison en 24 à 48h</div>
</div>

</div>

<div class="list-group-item d-flex gap-3 align-items-start px-0" style="background:transparent;">

<div class="icone-engagement">
<i class="bi bi-shield-check"></i>
</div>

<div>
<div class="fw-semibold small">Paiement sécurisé</div>
<div class="text-muted small">Vos paiements sont protégés</div>
</div>

</div>

<div class="list-group-item d-flex gap-3 align-items-start px-0" style="background:transparent;">

<div class="icone-engagement">
<i class="bi bi-award"></i>
</div>

<div>
<div class="fw-semibold small">Produits authentiques</div>
<div class="text-muted small">Vins 100% authentiques</div>
</div>

</div>

<div class="list-group-item d-flex gap-3 align-items-start px-0" style="background:transparent;">

<div class="icone-engagement">
<i class="bi bi-headset"></i>
</div>

<div>
<div class="fw-semibold small">Service client</div>
<div class="text-muted small">À votre écoute 7j/7</div>
</div>

</div>

</div>

</div>

</div>

</div>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     PIED DE PAGE
===================================================== -->

<footer class="pied-cave">

    <div class="container">

        <div class="row g-4">

            <div class="col-md-4">

                <h6>🍷 Cave à Vins</h6>

                <p class="small">
                    Votre cave en ligne au Bénin : une sélection de vins
                    rouges, blancs et champagnes livrés chez vous.
                </p>

                <div class="reseaux-sociaux mt-3">

                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-whatsapp"></i></a>

                </div>

            </div>


            <div class="col-md-2">

                <h6>Mon compte</h6>

                <ul class="list-unstyled">
                    <?php if(isset($_SESSION["client_id"])): ?>
                        <li class="mb-2"><a href="../client/mes_commandes.php">Mes commandes</a></li>
                        <li class="mb-2"><a href="../client/mes_notifications.php">Notifications</a></li>
                        <li class="mb-2"><a href="../client/modifier_client.php">Mon profil</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="../client/connexion_client.php">Connexion</a></li>
                        <li class="mb-2"><a href="../client/inscription.php">Créer un compte</a></li>
                    <?php endif; ?>
                    <li class="mb-2"><a href="panier.php">Mon panier</a></li>
                </ul>

            </div>


            <div class="col-md-2">

                <h6>Aide</h6>

                <ul class="list-unstyled">
                    <li class="mb-2"><a href="../avis/donner_avis.php">Donner un avis</a></li>
                    <li class="mb-2"><a href="../client/accueil_client.php">Assistant en ligne</a></li>
                </ul>

            </div>


            <div class="col-md-4">

                <h6>Newsletter</h6>

                <p class="small">
                    Recevez nos nouveautés et promotions.
                </p>

                <form class="newsletter d-flex" onsubmit="return false;">

                    <input type="email" class="form-control" placeholder="Votre email">

                    <button type="submit">
                        <i class="bi bi-send"></i>
                    </button>

                </form>

            </div>

        </div>


        <div class="copyright-cave text-center">
            © <?php echo date("Y"); ?> Cave à Vins — Tous droits réservés.
        </div>

    </div>

</footer>


<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>

function stepQty(btn, delta)
{
    var form  = btn.closest("form");
    var input = form.querySelector('input[name="quantite"]');
    var max   = parseInt(input.getAttribute("max"), 10);
    var min   = parseInt(input.getAttribute("min"), 10) || 1;
    var value = parseInt(input.value, 10) || min;

    value += delta;

    if(value < min){ value = min; }
    if(max && value > max){ value = max; }

    input.value = value;
    form.submit();
}

</script>

</body>

</html>