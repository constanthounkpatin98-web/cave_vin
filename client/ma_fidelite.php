<?php

session_start();

require_once("../connexion.php");
require_once("fonctions_fidelite.php");

//===============================================
// Sécurité : client connecté
//===============================================

if (!isset($_SESSION["client_id"])) {
    header("Location: connexion_client.php");
    exit();
}

$id_client = $_SESSION["client_id"];

//===============================================
// Récupération des données fidélité
//===============================================

$fidelite = obtenir_fidelite_client($connexion, $id_client);

$points_actuels                 = (int) $fidelite["points_actuels"];
$total_depense                  = (float) $fidelite["total_depense"];
$recompenses_disponibles        = (int) $fidelite["nombre_recompenses_disponibles"];

$points_dans_palier = $points_actuels % 50;
$pourcentage        = (int) round(($points_dans_palier / 50) * 100);
$points_restants     = 50 - $points_dans_palier;
if ($points_dans_palier === 0 && $points_actuels > 0) {
    $points_restants = 0;
}

$vins_disponibles = vins_eligibles_recompense($connexion);

//===============================================
// Message de retour (après utilisation d'une récompense)
//===============================================

$message_succes = $_SESSION["fidelite_message_succes"] ?? null;
$message_erreur = $_SESSION["fidelite_message_erreur"] ?? null;
unset($_SESSION["fidelite_message_succes"], $_SESSION["fidelite_message_erreur"]);

//===============================================
// Nombre d'articles dans le panier
//===============================================

$nombre_panier = isset($_SESSION["panier"])
    ? array_sum($_SESSION["panier"])
    : 0;

//===============================================
// Nombre de notifications non lues
//===============================================

$requete_notif = $connexion->prepare("

    SELECT COUNT(*) AS total
    FROM notification
    WHERE id_client = ? AND statut = 'Non lue'

");

$requete_notif->execute([$id_client]);

$nombre_notifications = $requete_notif->fetch()["total"];

//===============================================
// Nombre de commandes (pour le lien d'entête)
//===============================================

$requete_commandes = $connexion->prepare("

    SELECT COUNT(*) AS total
    FROM commande
    WHERE id_client = ?

");

$requete_commandes->execute([$id_client]);

$nombre_commandes_client = $requete_commandes->fetch()["total"];

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Ma fidélité — Cave à Vins</title>

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
       BLOC FIDELITE
    ===================================================== */

    .carte-fidelite {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);
        overflow: hidden;
    }

    .entete-carte {
        background: var(--noir);
        color: #fff;
        padding: 18px 22px;
    }

    .entete-carte h3 {
        font-size: 1.3rem;
        margin: 0;
    }

    .badge-recompense {
        background: linear-gradient(135deg, var(--or), #e3c67a);
        color: var(--noir);
        border-radius: 10px;
        padding: 14px clamp(14px, 3vw, 20px);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .barre-progression-fidelite {
        height: 22px;
        border-radius: 11px;
        background: #efe8de;
        overflow: hidden;
    }

    .barre-progression-fidelite .remplissage {
        height: 100%;
        background: linear-gradient(90deg, var(--bordeaux), var(--or));
        transition: width .4s ease;
    }

    .stat-fidelite {
        text-align: center;
        padding: clamp(10px, 2.5vw, 14px);
        border-radius: 10px;
        background: var(--creme);
    }

    .stat-fidelite .valeur {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.1rem, 4vw, 1.6rem);
        font-weight: 700;
        color: var(--bordeaux);
        white-space: nowrap;
    }

    .stat-fidelite .libelle {
        font-size: clamp(0.62rem, 1.8vw, 0.8rem);
        color: #8a8078;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .carte-vin-recompense {
        border: 1px solid #efe8de;
        border-radius: 10px;
        padding: 14px;
        height: 100%;
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


    @media (max-width: 767.98px) {

        .container {
            padding-left: 12px;
            padding-right: 12px;
        }

        .container.my-5 {
            margin-top: 24px !important;
            margin-bottom: 24px !important;
        }

        /* ---------- Barre supérieure ---------- */

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

        /* ---------- En-tête ---------- */

        .entete {
            padding: 12px 0;
        }

        .entete .container {
            gap: 10px !important;
        }

        .logo-cave {
            font-size: 1.05rem;
        }

        /* ---------- Fil d'Ariane ---------- */

        .fil-ariane {
            padding: 10px 0;
            font-size: .78rem;
        }

        /* ---------- Carte fidélité ---------- */

        .entete-carte {
            padding: 14px 16px;
        }

        .entete-carte h3 {
            font-size: 1.1rem;
        }

        .carte-fidelite > .p-4 {
            padding: 14px !important;
        }

        /* ---------- Bandeau livraison ---------- */

        .bandeau-livraison {
            padding: 24px 0;
            text-align: center;
        }

        .bandeau-livraison .d-flex.align-items-center.gap-3 {
            justify-content: center;
        }

        /* ---------- Footer : une colonne ---------- */

        .pied-cave {
            padding: 40px 0 24px;
        }

        .pied-cave .row {
            --bs-gutter-y: 1.8rem;
        }

        .pied-cave [class*="col-"] {
            width: 100%;
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


    @media (max-width: 380px) {

        .container {
            padding-left: 9px;
            padding-right: 9px;
        }
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

        <a href="accueil_client.php" class="logo-cave">
            🍷 CAVE <span>À VINS</span>
        </a>


        <div class="d-none d-lg-flex align-items-center gap-4">

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

                <a href="mes_commandes.php">
                    <i class="bi bi-receipt"></i>
                    Mes commandes
                </a>

            <?php endif; ?>


            <a href="ma_fidelite.php">
                <i class="bi bi-gift"></i>
                Ma fidélité
            </a>


            <a href="mes_notifications.php" class="position-relative">

                <i class="bi bi-bell fs-5"></i>

                <?php if ($nombre_notifications > 0): ?>

                    <span class="badge-panier">
                        <?php echo $nombre_notifications; ?>
                    </span>

                <?php endif; ?>

            </a>


            <a href="../panier/panier.php" class="position-relative">

                <i class="bi bi-cart3 fs-5"></i>

                <?php if ($nombre_panier > 0): ?>

                    <span class="badge-panier">
                        <?php echo $nombre_panier; ?>
                    </span>

                <?php endif; ?>

            </a>


            <a href="deconnexion_client.php">
                <i class="bi bi-box-arrow-right"></i>
                Déconnexion
            </a>

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
            Bonjour, <?php echo htmlspecialchars($_SESSION["client_nom"] ?? ""); ?>
        </h5>

        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>

    </div>

    <div class="offcanvas-body d-flex flex-column gap-2">

        <a href="../avis/donner_avis.php" class="lien-sidebar">
            <i class="bi bi-star"></i> Avis
        </a>

        <?php if ($nombre_commandes_client > 0): ?>

            <a href="mes_commandes.php" class="lien-sidebar">
                <i class="bi bi-receipt"></i> Mes commandes
            </a>

        <?php endif; ?>

        <a href="ma_fidelite.php" class="lien-sidebar">
            <i class="bi bi-gift"></i> Ma fidélité
        </a>

        <a href="mes_notifications.php" class="lien-sidebar">
            <i class="bi bi-bell"></i> Notifications
            <?php if ($nombre_notifications > 0): ?>
                <span class="badge-panier"><?php echo $nombre_notifications; ?></span>
            <?php endif; ?>
        </a>

        <a href="deconnexion_client.php" class="lien-sidebar">
            <i class="bi bi-box-arrow-right"></i> Déconnexion
        </a>

    </div>

</div>

<div class="fil-ariane">

    <div class="container">

        <a href="accueil_client.php">Accueil</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <span class="actif">Ma fidélité</span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container my-5">

    <div class="carte-fidelite">

        <div class="entete-carte d-flex justify-content-between align-items-center flex-wrap gap-2">

            <h3><i class="bi bi-gift"></i> Ma fidélité</h3>

            <a href="accueil_client.php" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left"></i> Retour au catalogue
            </a>

        </div>

        <div class="p-4">

            <?php if ($message_succes): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message_succes); ?></div>
            <?php endif; ?>

            <?php if ($message_erreur): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($message_erreur); ?></div>
            <?php endif; ?>

            <?php if ($recompenses_disponibles > 0): ?>
            <div class="badge-recompense">
                <i class="bi bi-gift-fill fs-3"></i>
                <div>
                    Vous avez <?php echo $recompenses_disponibles; ?> récompense<?php echo $recompenses_disponibles > 1 ? "s" : ""; ?> disponible<?php echo $recompenses_disponibles > 1 ? "s" : ""; ?> !
                    Choisissez votre bouteille ci-dessous.
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-4">
                    <div class="stat-fidelite">
                        <div class="valeur"><?php echo $points_actuels; ?></div>
                        <div class="libelle">Points</div>
                    </div>
                </div>
                <div class="col-6 col-sm-4">
                    <div class="stat-fidelite">
                        <div class="valeur"><?php echo number_format($total_depense, 0, ',', ' '); ?></div>
                        <div class="libelle">FCFA dépensés</div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="stat-fidelite">
                        <div class="valeur"><?php echo $recompenses_disponibles; ?></div>
                        <div class="libelle">Récompenses</div>
                    </div>
                </div>
            </div>

            <div class="mb-2 d-flex justify-content-between">
                <span>⭐ Mes points : <?php echo $points_dans_palier; ?> / 50</span>
            </div>

            <div class="barre-progression-fidelite mb-2">
                <div class="remplissage" style="width: <?php echo $pourcentage; ?>%;"></div>
            </div>

            <div class="text-muted small mb-4">
                <?php if ($points_restants > 0): ?>
                    Encore <?php echo $points_restants; ?> point<?php echo $points_restants > 1 ? "s" : ""; ?> avant votre prochaine récompense.
                <?php else: ?>
                    🎁 Palier atteint — profitez de votre récompense ci-dessous !
                <?php endif; ?>
            </div>

            <hr>

            <h5 class="mb-3">Vins éligibles à une récompense (≤ 10 000 FCFA)</h5>

            <?php if ($recompenses_disponibles <= 0): ?>
            <p class="text-muted">Vous n'avez pas encore assez de points pour échanger une récompense.</p>
            <?php elseif (empty($vins_disponibles)): ?>
            <p class="text-muted">Aucun vin éligible n'est disponible pour le moment.</p>
            <?php else: ?>

            <div class="row g-3">

                <?php foreach ($vins_disponibles as $vin): ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="carte-vin-recompense">

                        <?php if (!empty($vin["photo"])): ?>
                        <img src="../vin/uploads/<?php echo htmlspecialchars($vin["photo"]); ?>" class="img-fluid rounded mb-2" alt="<?php echo htmlspecialchars($vin["nom_vin"]); ?>">
                        <?php endif; ?>

                        <h6 class="mb-1"><?php echo htmlspecialchars($vin["nom_vin"]); ?></h6>
                        <div class="text-muted small mb-2"><?php echo number_format($vin["prix"], 0, ',', ' '); ?> FCFA</div>

                        <form action="utiliser_recompense.php" method="POST">
                            <input type="hidden" name="id_vin" value="<?php echo (int) $vin["id_vin"]; ?>">
                            <button type="submit" class="btn btn-bordeaux btn-sm w-100">
                                <i class="bi bi-gift"></i> Utiliser ma récompense
                            </button>
                        </form>

                    </div>
                </div>
                <?php endforeach; ?>

            </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =====================================================
     BANDEAU LIVRAISON
===================================================== -->

<div class="bandeau-livraison">

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
                    <li class="mb-2"><a href="mes_commandes.php">Mes commandes</a></li>
                    <li class="mb-2"><a href="ma_fidelite.php">Ma fidélité</a></li>
                    <li class="mb-2"><a href="mes_notifications.php">Notifications</a></li>
                    <li class="mb-2"><a href="modifier_client.php">Mon profil</a></li>
                    <li class="mb-2"><a href="../panier/panier.php">Mon panier</a></li>
                </ul>

            </div>


            <div class="col-md-2">

                <h6>Aide</h6>

                <ul class="list-unstyled">
                    <li class="mb-2"><a href="../avis/donner_avis.php">Donner un avis</a></li>
                    <li class="mb-2"><a href="accueil_client.php">Assistant en ligne</a></li>
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