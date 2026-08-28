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
// Traitement de l'envoi de l'avis
//===============================================

if(isset($_POST["note"]))
{
    $note        = (int)$_POST["note"];
    $commentaire = trim($_POST["commentaire"]);
    $id_client   = $_SESSION["client_id"];

    if($note < 1 || $note > 5 || empty($commentaire))
    {
        header("Location: donner_avis.php?erreur=1");
        exit();
    }

    $requete = $connexion->prepare("

    INSERT INTO avis (note, commentaire, id_client, statut)

    VALUES (?, ?, ?, 'En attente')

    ");

    $requete->execute([$note, $commentaire, $id_client]);

    header("Location: donner_avis.php?ajout=ok");
    exit();
}


//===============================================
// Informations d'entête (panier / notifications / commandes)
//===============================================

$nombre_panier = isset($_SESSION["panier"])
    ? array_sum($_SESSION["panier"])
    : 0;

$requete_notif = $connexion->prepare("

    SELECT COUNT(*) AS total
    FROM notification
    WHERE id_client = ? AND statut = 'Non lue'

");

$requete_notif->execute([$_SESSION["client_id"]]);

$nombre_notifications = $requete_notif->fetch()["total"];

$requete_commandes_total = $connexion->prepare("

    SELECT COUNT(*) AS total
    FROM commande
    WHERE id_client = ?

");

$requete_commandes_total->execute([$_SESSION["client_id"]]);

$nombre_commandes_client = $requete_commandes_total->fetch()["total"];

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Laisser un avis</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css">

<style>

    /* =====================================================
       VARIABLES (polices systeme, tout en local)
    ===================================================== */

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
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #2c2622;

    }


    h1, h2, h3, h4, h5, h6 {

        font-family: Georgia, 'Times New Roman', serif;

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

        font-family: Georgia, 'Times New Roman', serif;
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
       CARTE AVIS
    ===================================================== */

    .carte-cave {

        border: 1px solid #efe8de;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(28,20,20,.06);
        background: #fff;

    }


    .entete-carte {

        background: var(--noir);
        color: #fff;
        padding: 22px;
        text-align: center;
        border-bottom: 3px solid var(--bordeaux);

    }


    .entete-carte .icone {

        font-size: 1.8rem;
        margin-bottom: 4px;

    }


    .entete-carte h3 {

        margin: 0;
        font-size: 1.3rem;

    }


    .entete-carte p {

        color: #cfc8c2;
        font-size: .85rem;
        margin: 4px 0 0;

    }


    /* =====================================================
       SELECTEUR D'ETOILES
    ===================================================== */

    .selecteur-etoiles {

        display: flex;
        gap: 8px;
        justify-content: center;
        font-size: 2.2rem;
        margin-bottom: 6px;

    }


    .selecteur-etoiles i {

        color: #ddd2c2;
        cursor: pointer;
        transition: color .15s ease, transform .1s ease;

    }


    .selecteur-etoiles i:hover,
    .selecteur-etoiles i.survolee {

        transform: scale(1.1);

    }


    .selecteur-etoiles i.remplie {

        color: var(--or);

    }


    .libelle-note {

        text-align: center;
        font-weight: 600;
        color: var(--bordeaux);
        min-height: 1.3em;
        margin-bottom: 18px;

    }


    .form-label {

        font-weight: 600;
        font-size: .88rem;
        color: #4a423c;

    }


    .form-control {

        border: 1px solid #e6ddd1;
        border-radius: 8px;
        padding: 10px 14px;

    }


    .form-control:focus {

        border-color: var(--bordeaux);
        box-shadow: 0 0 0 3px rgba(109,22,38,.1);

    }


    .btn-bordeaux {

        background: var(--bordeaux);
        border-color: var(--bordeaux);
        color: #fff;
        font-weight: 600;
        padding: 10px 22px;

    }


    .btn-bordeaux:hover {

        background: var(--bordeaux-fonce);
        border-color: var(--bordeaux-fonce);
        color: #fff;

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
        font-family: Georgia, 'Times New Roman', serif;
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

        font-family: Georgia, 'Times New Roman', serif;
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

        .container.py-4.py-md-5 {
            padding-top: 20px !important;
            padding-bottom: 20px !important;
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

        /* ---------- Carte avis ---------- */

        .entete-carte {
            padding: 18px;
        }

        .entete-carte h3 {
            font-size: 1.15rem;
        }

        .carte-cave > .p-4 {
            padding: 16px !important;
        }

        .selecteur-etoiles {
            font-size: 1.9rem;
            gap: 6px;
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

        .selecteur-etoiles {
            font-size: 1.6rem;
            gap: 4px;
        }

        .entete-carte h3 {
            font-size: 1.05rem;
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

        <a href="../client/accueil_client.php" class="logo-cave">
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


            <a href="donner_avis.php">
                <i class="bi bi-star-fill"></i>
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


            <a href="../panier/panier.php" class="position-relative">

                <i class="bi bi-cart3 fs-5"></i>

                <?php if ($nombre_panier > 0): ?>

                    <span class="badge-panier">
                        <?php echo $nombre_panier; ?>
                    </span>

                <?php endif; ?>

            </a>


            <a href="../client/deconnexion_client.php">
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

        <a href="donner_avis.php" class="lien-sidebar">
            <i class="bi bi-star-fill"></i> Avis
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

    </div>

</div>


<!-- =====================================================
     FIL D'ARIANE
===================================================== -->

<div class="fil-ariane">

    <div class="container">

        <a href="../client/accueil_client.php">Accueil</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <span class="actif">Laisser un avis</span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container py-4 py-md-5">

<div class="row justify-content-center">

<div class="col-lg-6">

<div class="carte-cave">

<div class="entete-carte">

<div class="icone">🍷</div>

<h3>Laisser un avis</h3>

<p>Votre expérience compte pour nous</p>

</div>

<div class="p-4">

<?php if(isset($_GET['ajout'])): ?>

<div class="alert alert-success">
<i class="bi bi-check-circle"></i>
Merci pour votre avis ! Il sera publié après validation.
</div>

<?php endif; ?>

<?php if(isset($_GET['erreur'])): ?>

<div class="alert alert-danger">
<i class="bi bi-exclamation-triangle"></i>
Veuillez donner une note et un commentaire.
</div>

<?php endif; ?>

<form action="donner_avis.php" method="POST" id="formulaireAvis">

<label class="form-label d-block text-center">Votre note</label>

<div class="selecteur-etoiles" id="selecteurEtoiles">

<i class="bi bi-star" data-valeur="1"></i>
<i class="bi bi-star" data-valeur="2"></i>
<i class="bi bi-star" data-valeur="3"></i>
<i class="bi bi-star" data-valeur="4"></i>
<i class="bi bi-star" data-valeur="5"></i>

</div>

<div class="libelle-note" id="libelleNote">&nbsp;</div>

<input type="hidden" name="note" id="champNote" required>

<div class="mb-3">

<label class="form-label">Votre commentaire</label>

<textarea name="commentaire" class="form-control" rows="4" placeholder="Racontez-nous votre expérience..." required></textarea>

</div>

<button type="submit" class="btn btn-bordeaux w-100">
<i class="bi bi-send"></i>
Envoyer mon avis
</button>

</form>

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
                    <li class="mb-2"><a href="../client/mes_commandes.php">Mes commandes</a></li>
                    <li class="mb-2"><a href="../client/mes_notifications.php">Notifications</a></li>
                    <li class="mb-2"><a href="../client/modifier_client.php">Mon profil</a></li>
                    <li class="mb-2"><a href="../panier/panier.php">Mon panier</a></li>
                </ul>

            </div>


            <div class="col-md-2">

                <h6>Aide</h6>

                <ul class="list-unstyled">
                    <li class="mb-2"><a href="donner_avis.php">Donner un avis</a></li>
                    <li class="mb-2"><a href="../client/accueil_client.php">Assistant en ligne</a></li>
                </ul>

            </div>


            <div class="col-md-4">

                <h6>Newsletter</h6>

                <p class="small">
                    Recevez nos nouveautés et promotions.
                </p>

            </div>

        </div>


        <div class="copyright-cave text-center">
            © <?php echo date("Y"); ?> Cave à Vins — Tous droits réservés.
        </div>

    </div>

</footer>


<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>

    //===========================================================
    // Sélecteur d'étoiles interactif (remplit le champ caché "note")
    //===========================================================

    (function () {

        const etoiles      = document.querySelectorAll("#selecteurEtoiles i");
        const champNote    = document.getElementById("champNote");
        const libelleNote  = document.getElementById("libelleNote");
        const formulaire   = document.getElementById("formulaireAvis");

        const libelles = {
            1: "1 - Décevant",
            2: "2 - Moyen",
            3: "3 - Bien",
            4: "4 - Très bien",
            5: "5 - Excellent",
        };

        let noteChoisie = 0;

        function dessiner(valeurAffichee) {

            etoiles.forEach(function (etoile) {

                const v = parseInt(etoile.getAttribute("data-valeur"), 10);

                if (v <= valeurAffichee) {
                    etoile.classList.add("remplie");
                    etoile.classList.remove("bi-star");
                    etoile.classList.add("bi-star-fill");
                } else {
                    etoile.classList.remove("remplie");
                    etoile.classList.remove("bi-star-fill");
                    etoile.classList.add("bi-star");
                }

            });

        }

        etoiles.forEach(function (etoile) {

            const v = parseInt(etoile.getAttribute("data-valeur"), 10);

            etoile.addEventListener("mouseenter", function () {
                dessiner(v);
            });

            etoile.addEventListener("mouseleave", function () {
                dessiner(noteChoisie);
            });

            etoile.addEventListener("click", function () {
                noteChoisie = v;
                champNote.value = v;
                libelleNote.textContent = libelles[v];
                dessiner(v);
            });

        });

        formulaire.addEventListener("submit", function (e) {

            if (!champNote.value) {
                e.preventDefault();
                libelleNote.textContent = "Veuillez choisir une note";
                libelleNote.style.color = "#b3261e";
            }

        });

    })();

</script>

</body>

</html>