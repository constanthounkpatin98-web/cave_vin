<?php

session_start();

require_once("../connexion.php");

//===============================================
// Vérification de l'existence du vin (publique)
//===============================================

if(!isset($_GET["id_vin"]))
{
    header("Location: ../client/accueil_client.php");
    exit();
}

$id_vin = $_GET["id_vin"];

//===============================================
// Récupération du vin
//===============================================

$requete = $connexion->prepare("

SELECT vin.*, categorie.libelle AS libelle_categorie

FROM vin

LEFT JOIN categorie ON vin.id_categorie = categorie.id_categorie

WHERE vin.id_vin = ?

");

$requete->execute([$id_vin]);
$vin = $requete->fetch();

if(!$vin)
{
    header("Location: ../client/accueil_client.php?erreur=introuvable");
    exit();
}

//===============================================
// Avis publiés liés
//===============================================

$requete_avis = $connexion->query("

SELECT AVG(note) AS moyenne, COUNT(*) AS total

FROM avis

WHERE statut = 'Publié'

");

$stat_avis = $requete_avis->fetch();

//===============================================
// Quelques vins similaires
//===============================================

$requete_similaires = $connexion->prepare("

SELECT * FROM vin

WHERE id_categorie = ? AND id_vin != ? AND statut = 'Disponible' AND quantite_stock > 0

LIMIT 4

");

$requete_similaires->execute([$vin["id_categorie"], $id_vin]);

//===============================================
// Nombre d'articles dans le panier
//===============================================

$nombre_panier = isset($_SESSION["panier"])
    ? array_sum($_SESSION["panier"])
    : 0;

//===============================================
// Nombre de notifications non lues (seulement si connecté)
//===============================================

$nombre_notifications = 0;
$nombre_commandes_client = 0;

if (isset($_SESSION["client_id"])) {
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

<title><?php echo htmlspecialchars($vin["nom_vin"]); ?></title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css">

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
       DETAIL DU VIN
    ===================================================== */

    .image-detail {

        width: 100%;
        max-height: 480px;
        object-fit: contain;
        border-radius: 12px;
        background: #efe8de;
        border: 1px solid #efe8de;
        padding: 16px;

    }


    .ruban-cat-detail {

        display: inline-block;
        background: var(--noir);
        color: var(--or);
        font-size: .7rem;
        letter-spacing: .06em;
        text-transform: uppercase;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
        margin-bottom: 10px;

    }


    .nom-vin-detail {

        font-weight: 800;
        font-size: 2rem;

    }


    .prix-detail {

        font-size: 1.9rem;
        font-weight: 700;
        color: var(--bordeaux);
        font-family: 'Playfair Display', serif;

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


    .badge-stock-detail {

        background: #e9f4ec;
        color: #1f7a45;
        font-weight: 600;

    }


    .badge-rupture-detail {

        background: #fbe9e9;
        color: #b3261e;
        font-weight: 600;

    }


    table.table-infos th {

        color: #6a615a;
        font-weight: 600;
        font-size: .88rem;

    }


    /* =====================================================
       SECTION TITRE
    ===================================================== */

    .titre-section {

        display: flex;
        align-items: center;
        gap: 14px;
        margin: 46px 0 26px;

    }


    .titre-section h2 {

        font-weight: 700;
        font-size: 1.5rem;
        margin: 0;
        white-space: nowrap;

    }


    .titre-section .trait {

        flex: 1;
        height: 1px;
        background: linear-gradient(to right, #ddd2c2, transparent);

    }


    /* =====================================================
       VINS SIMILAIRES
    ===================================================== */

    .carte-vin-mini {

        border: 1px solid #efe8de;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(28,20,20,.05);
        height: 100%;
        background: #fff;
        transition: transform .15s ease, box-shadow .15s ease;

    }


    .carte-vin-mini:hover {

        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(28,20,20,.12);

    }


    .image-vin-mini {

        height: 150px;
        object-fit: contain;
        background: #efe8de;
        padding: 8px;

    }


    /* =====================================================
       BANDEAU LIVRAISON
    ===================================================== */

    .bandeau-livraison {

        background: var(--bordeaux);
        color: #fff;
        padding: 34px 0;
        margin-top: 56px;

    }


    .bandeau-livraison .pastille {

        width: 64px;
        height: 64px;
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,.55);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: .95rem;
        text-align: center;
        flex-direction: column;
        line-height: 1.05;

    }


    /* =====================================================
       PIED DE PAGE
    ===================================================== */

    .pied-cave {

        background: var(--noir);
        color: #beb4ab;
        padding: 44px 0 22px;
        font-size: .9rem;

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


/* ===== RESPONSIVE : évite tout débordement horizontal ===== */
*, *::before, *::after { box-sizing: border-box; }

html, body {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

img, table, input, button, select, textarea {
    max-width: 100%;
}

.container {
    width: 100%;
    max-width: 1320px;
    min-width: 0;
}

.barre-haut,
.entete,
.fil-ariane,
.pied-cave {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.image-detail {
    display: block;
    width: 100%;
    height: auto;
}

.nom-vin-detail { overflow-wrap: anywhere; }

.fil-ariane .container {
    white-space: normal;
    overflow-wrap: anywhere;
}


/* =====================================================
   TABLETTE : jusqu'à 991px
===================================================== */

@media (max-width: 991.98px) {

    .image-detail { max-height: 420px; }

    .nom-vin-detail { font-size: 1.75rem; }

    .prix-detail { font-size: 1.65rem; }

    .titre-section h2 { white-space: normal; }
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
        line-height: 1.7;
    }

    /* Détail */
    .container.mt-4 {
        margin-top: 20px !important;
    }

    .container.mt-4 > .row {
        --bs-gutter-y: 28px;
    }

    .image-detail {
        width: 100%;
        height: min(72vw, 360px);
        max-height: none;
        padding: 10px;
        border-radius: 12px;
        object-fit: contain;
    }

    .nom-vin-detail {
        font-size: clamp(1.45rem, 7vw, 2rem);
        line-height: 1.2;
        margin-bottom: 8px !important;
    }

    .ruban-cat-detail {
        font-size: .62rem;
        padding: 5px 10px;
        margin-bottom: 8px;
    }

    .prix-detail {
        font-size: 1.55rem;
        line-height: 1.2;
        margin: 12px 0 18px !important;
    }

    .table-infos {
        width: 100% !important;
        margin-bottom: 20px !important;
        font-size: .78rem;
    }

    .table-infos th {
        width: 44%;
        padding: 7px 10px 7px 0 !important;
        font-size: .76rem !important;
        vertical-align: top;
    }

    .table-infos td {
        padding: 7px 0 !important;
        overflow-wrap: anywhere;
    }

    .badge-stock-detail,
    .badge-rupture-detail {
        display: inline-block;
        white-space: normal;
        line-height: 1.4;
        padding: 5px 8px;
    }

    .container.mt-4 h6.fw-bold {
        margin-top: 24px;
        margin-bottom: 9px;
    }

    .container.mt-4 p {
        line-height: 1.7;
    }

    /* Boutons */
    .container.mt-4 .d-flex.gap-2.mt-4 {
        display: flex !important;
        flex-direction: column;
        align-items: stretch !important;
        gap: 12px !important;
        margin-top: 24px !important;
    }

    .container.mt-4 .d-flex.gap-2.mt-4 > a,
    .container.mt-4 .d-flex.gap-2.mt-4 > form,
    .container.mt-4 .d-flex.gap-2.mt-4 > .alert {
        width: 100%;
        max-width: 100%;
    }

    .container.mt-4 .d-flex.gap-2.mt-4 > a {
        text-align: center;
        padding: 11px 14px;
    }

    .container.mt-4 .d-flex.gap-2.mt-4 > form {
        display: flex !important;
        gap: 10px !important;
    }

    .container.mt-4 .d-flex.gap-2.mt-4 > form input[type="number"] {
        width: 68px !important;
        min-width: 68px;
        flex: 0 0 68px;
    }

    .container.mt-4 .d-flex.gap-2.mt-4 > form button {
        min-width: 0;
        padding: 11px 10px;
        white-space: normal;
        line-height: 1.3;
    }

    /* Vins similaires */
    .titre-section {
        margin: 30px 0 16px;
    }

    .titre-section h2 {
        font-size: 1.1rem;
        white-space: normal;
    }

    .row.g-3.mb-5 {
        --bs-gutter-x: 10px;
        --bs-gutter-y: 14px;
    }

    .row.g-3.mb-5 > .col-md-3 {
        width: 50%;
        flex: 0 0 50%;
    }

    .carte-vin-mini { min-width: 0; }

    .image-vin-mini {
        height: 135px;
        padding: 7px;
    }

    .carte-vin-mini .card-body { padding: 9px !important; }

    .carte-vin-mini .text-truncate { font-size: .72rem; }

    .carte-vin-mini .small { font-size: .67rem; }

    /* Bandeau */
    .bandeau-livraison {
        margin-top: 38px !important;
        padding: 34px 0 40px;
    }

    .bandeau-livraison .container {
        display: flex;
        flex-direction: column;
        align-items: stretch !important;
        justify-content: flex-start !important;
        gap: 27px !important;
    }

    .bandeau-livraison .container > div {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 14px !important;
        min-width: 0;
    }

    .bandeau-livraison .pastille {
        width: 60px;
        height: 60px;
        min-width: 60px;
        flex: 0 0 60px;
        font-size: .7rem;
    }

    .bandeau-livraison .fs-2 {
        font-size: 1.75rem !important;
        min-width: 34px;
    }

    .bandeau-livraison .fw-bold {
        font-size: 1.05rem;
        line-height: 1.3;
        margin-bottom: 5px;
    }

    .bandeau-livraison .small {
        font-size: .84rem !important;
        line-height: 1.55;
        overflow-wrap: anywhere;
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

    .logo-cave { font-size: .9rem; }

    .image-detail { height: 68vw; }

    .nom-vin-detail { font-size: 1.4rem; }

    .prix-detail { font-size: 1.4rem; }

    .table-infos { font-size: .72rem; }

    .table-infos th { font-size: .7rem !important; }

    .image-vin-mini { height: 120px; }

    .bandeau-livraison .container {
        gap: 23px !important;
    }

    .bandeau-livraison .pastille {
        width: 55px;
        height: 55px;
        min-width: 55px;
        flex-basis: 55px;
    }

    .pied-cave [class*="col-"] {
        margin-bottom: 34px;
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


            <a href="../panier/panier.php" class="position-relative">

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

            <a href="../panier/panier.php" class="position-relative text-white">

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


<!-- =====================================================
     FIL D'ARIANE
===================================================== -->

<div class="fil-ariane">

    <div class="container">

        <a href="../client/accueil_client.php">Accueil</a>

        <?php if (!empty($vin["libelle_categorie"])): ?>

            <i class="bi bi-chevron-right mx-1"></i>

            <a href="../client/accueil_client.php?categorie=<?php echo $vin["id_categorie"]; ?>">
                <?php echo htmlspecialchars($vin["libelle_categorie"]); ?>
            </a>

        <?php endif; ?>

        <i class="bi bi-chevron-right mx-1"></i>

        <span class="actif"><?php echo htmlspecialchars($vin["nom_vin"]); ?></span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container mt-4">

<div class="row g-4">

<div class="col-md-6">

<?php if(!empty($vin["photo"])): ?>

<img src="uploads/<?php echo htmlspecialchars($vin["photo"]); ?>" class="image-detail" alt="<?php echo htmlspecialchars($vin["nom_vin"]); ?>">

<?php else: ?>

<div class="image-detail d-flex align-items-center justify-content-center text-muted fs-1">🍷</div>

<?php endif; ?>

</div>

<div class="col-md-6">

<?php if (!empty($vin["libelle_categorie"])): ?>

<span class="ruban-cat-detail"><?php echo htmlspecialchars($vin["libelle_categorie"]); ?></span>

<?php endif; ?>

<h2 class="nom-vin-detail mb-1"><?php echo htmlspecialchars($vin["nom_vin"]); ?></h2>

<p class="text-muted mb-3">

<?php echo htmlspecialchars($vin["couleur"]); ?>

<?php if(!empty($vin["millesime"])): ?>

• Millésime <?php echo htmlspecialchars($vin["millesime"]); ?>

<?php endif; ?>

</p>

<?php if($stat_avis["total"] > 0): ?>

<p class="mb-3" style="color:var(--or);">

<?php

$moyenne = round($stat_avis["moyenne"]);

echo str_repeat("★", $moyenne) . str_repeat("☆", 5 - $moyenne);

?>

<span class="text-muted small">(<?php echo $stat_avis["total"]; ?> avis clients)</span>

</p>

<?php endif; ?>

<p class="prix-detail mb-3"><?php echo number_format($vin["prix"], 0, ',', ' '); ?> FCFA</p>

<table class="table table-borderless table-infos w-auto mb-3">

<tbody>

<?php if(!empty($vin["pays_origine"])): ?>

<tr><th class="pe-4">Pays d'origine</th><td><?php echo htmlspecialchars($vin["pays_origine"]); ?></td></tr>

<?php endif; ?>

<?php if(!empty($vin["region"])): ?>

<tr><th class="pe-4">Région</th><td><?php echo htmlspecialchars($vin["region"]); ?></td></tr>

<?php endif; ?>

<?php if(!empty($vin["degre_alcool"])): ?>

<tr><th class="pe-4">Degré d'alcool</th><td><?php echo htmlspecialchars($vin["degre_alcool"]); ?> %</td></tr>

<?php endif; ?>

<tr><th class="pe-4">Disponibilité</th><td>

<?php if($vin["quantite_stock"] > 0): ?>

<span class="badge badge-stock-detail"><i class="bi bi-check-circle"></i> En stock (<?php echo $vin["quantite_stock"]; ?> bouteilles)</span>

<?php else: ?>

<span class="badge badge-rupture-detail"><i class="bi bi-x-circle"></i> Rupture de stock</span>

<?php endif; ?>

</td></tr>

<?php if(!empty($vin["points_fidelite_requis"]) && $vin["points_fidelite_requis"] > 0): ?>

<tr><th class="pe-4">Fidélité</th><td>

<span class="badge" style="background:var(--or); color:var(--noir);">
    <i class="bi bi-star-fill"></i>
    Réservé dès <?php echo $vin["points_fidelite_requis"]; ?> points
</span>

</td></tr>

<?php endif; ?>

</tbody>

</table>

<?php if(!empty($vin["description"])): ?>

<h6 class="fw-bold">Description</h6>

<p><?php echo nl2br(htmlspecialchars($vin["description"])); ?></p>

<?php endif; ?>

<div class="d-flex gap-2 mt-4 flex-wrap">

<a href="../client/accueil_client.php" class="btn btn-outline-noir">
<i class="bi bi-arrow-left"></i> Retour à la liste
</a>

<?php if($vin["quantite_stock"] > 0): ?>

<form action="../panier/ajouter_panier.php" method="POST" class="d-flex align-items-center gap-2 flex-grow-1">

<input type="hidden" name="id_vin" value="<?php echo $vin["id_vin"]; ?>">

<input type="number" name="quantite" value="1" min="1" max="<?php echo $vin["quantite_stock"]; ?>" class="form-control" style="width:90px;">

<button type="submit" class="btn btn-bordeaux flex-grow-1">

<i class="bi bi-cart-plus"></i> Ajouter au panier

</button>

</form>

<?php else: ?>

<div class="alert alert-secondary mb-0 flex-grow-1">Ce vin est momentanément indisponible.</div>

<?php endif; ?>

</div>

</div>

</div>

<?php if($requete_similaires->rowCount() > 0): ?>

<div class="titre-section">
    <h2>Vous aimerez peut-être aussi</h2>
    <div class="trait"></div>
</div>

<div class="row g-3 mb-5">

<?php while($similaire = $requete_similaires->fetch()): ?>

<div class="col-md-3">

<a href="voir_vin.php?id_vin=<?php echo $similaire["id_vin"]; ?>" class="text-decoration-none text-dark">

<div class="card carte-vin-mini">

<?php if(!empty($similaire["photo"])): ?>

<img src="uploads/<?php echo htmlspecialchars($similaire["photo"]); ?>" class="image-vin-mini w-100" alt="">

<?php else: ?>

<div class="image-vin-mini w-100 d-flex align-items-center justify-content-center text-muted small">🍷</div>

<?php endif; ?>

<div class="card-body py-2">

<div class="small fw-bold text-truncate"><?php echo htmlspecialchars($similaire["nom_vin"]); ?></div>

<div class="small" style="color:var(--bordeaux); font-weight:700;"><?php echo number_format($similaire["prix"], 0, ',', ' '); ?> FCFA</div>

</div>

</div>

</a>

</div>

<?php endwhile; ?>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     BANDEAU LIVRAISON
===================================================== -->

<div class="bandeau-livraison mt-5">

    <div class="container d-flex flex-wrap align-items-center justify-content-center justify-content-md-between gap-4">

        <div class="d-flex align-items-center gap-3">

            <div class="pastille">
                <span>Livraison<br>Gratuite</span>
            </div>

            <div>
                <div class="fw-bold">Livraison offerte</div>
                <div class="small" style="opacity:.85;">
                    Pour toute commande dans Porto-Novo et environs
                </div>
            </div>

        </div>


        <div class="d-flex align-items-center gap-3">

            <i class="bi bi-shield-check fs-2"></i>

            <div>
                <div class="fw-bold">Paiement sécurisé</div>
                <div class="small" style="opacity:.85;">
                    Mobile Money accepté
                </div>
            </div>

        </div>


        <div class="d-flex align-items-center gap-3">

            <i class="bi bi-award fs-2"></i>

            <div>
                <div class="fw-bold">Vins sélectionnés</div>
                <div class="small" style="opacity:.85;">
                    Qualité vérifiée en cave
                </div>
            </div>

        </div>

    </div>

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
                    <?php if (isset($_SESSION["client_id"])): ?>
                        <li class="mb-2"><a href="../client/mes_commandes.php">Mes commandes</a></li>
                        <li class="mb-2"><a href="../client/mes_notifications.php">Notifications</a></li>
                        <li class="mb-2"><a href="../client/modifier_client.php">Mon profil</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="../client/connexion_client.php">Connexion</a></li>
                        <li class="mb-2"><a href="../client/inscription.php">Créer un compte</a></li>
                    <?php endif; ?>
                    <li class="mb-2"><a href="../panier/panier.php">Mon panier</a></li>
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

</body>

</html>