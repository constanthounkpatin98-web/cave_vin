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
// Récupération de la commande et du paiement en attente
//===============================================

$id_commande = isset($_GET["id_commande"]) ? (int) $_GET["id_commande"] : 0;

$requete_commande = $connexion->prepare("SELECT * FROM commande WHERE id_commande = ? AND id_client = ?");
$requete_commande->execute([$id_commande, $_SESSION["client_id"]]);
$commande = $requete_commande->fetch();

if (!$commande) {
    header("Location: ../client/accueil_client.php");
    exit();
}

$requete_paiement = $connexion->prepare("SELECT * FROM paiement WHERE id_commande = ? ORDER BY id_paiement DESC LIMIT 1");
$requete_paiement->execute([$id_commande]);
$paiement = $requete_paiement->fetch();

if (!$paiement) {
    header("Location: effectuer_paiement.php?id_commande=" . $id_commande);
    exit();
}

// Déjà confirmé -> direction la page de succès
if ($paiement["statut"] === "Validé") {
    header("Location: confirmation_commande.php?id_commande=" . $id_commande);
    exit();
}

//===============================================
// Informations pour la navbar
//===============================================

$nombre_panier = isset($_SESSION["panier"]) ? array_sum($_SESSION["panier"]) : 0;

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

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Paiement en cours</title>

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
       PAIEMENT EN COURS
    ===================================================== */

    .carte-paiement {

        border: 1px solid #efe8de;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);
        background: #fff;

    }


    .entete-carte {

        background: var(--anthracite);
        color: #fff;
        padding: 18px 22px;
        border-bottom: 3px solid var(--bordeaux);

    }


    .entete-carte h3 {

        margin: 0;
        font-size: 1.3rem;

    }


    .entete-carte .sous-titre {

        color: #cfc8c2;
        font-size: .85rem;

    }


    .icone-attente {

        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #fbf1de;
        color: var(--or);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;

    }


    .icone-succes {

        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #e9f4ec;
        color: #1f7a45;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;

    }


    .spinner-personnalise {

        width: 3.5rem;
        height: 3.5rem;
        border: 4px solid #efe8de;
        border-top-color: var(--bordeaux);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        display: inline-block;

    }


    @keyframes spin {

        to { transform: rotate(360deg); }

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


    .total-commande {

        font-family: 'Playfair Display', serif;
        color: var(--bordeaux);
        font-size: 1.4rem;

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
       RESPONSIVE
    ===================================================== */

    @media (max-width: 768px) {

        .entete-carte h3 {
            font-size: 1.1rem;
        }

        .entete-carte .sous-titre {
            font-size: .75rem;
        }

        .total-commande {
            font-size: 1.1rem;
        }

        .icone-attente,
        .icone-succes {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }

        .spinner-personnalise {
            width: 2.8rem;
            height: 2.8rem;
        }

    }


    @media (max-width: 576px) {

        .carte-paiement .p-4 {
            padding: 1.2rem !important;
        }

        .entete-carte {
            padding: 12px 16px;
        }

        .entete-carte h3 {
            font-size: 1rem;
        }

        .total-commande {
            font-size: 1rem;
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


        <div class="d-flex align-items-center gap-4 flex-wrap">

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

    </div>

</div>


<!-- =====================================================
     FIL D'ARIANE
===================================================== -->

<div class="fil-ariane">

    <div class="container">

        <a href="../client/accueil_client.php">Accueil</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <a href="../panier/panier.php">Panier</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <span class="actif">Paiement en cours</span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container py-4 py-md-5">

<div class="row justify-content-center">

<div class="col-lg-7">

<div class="carte-paiement">

<div class="entete-carte d-flex justify-content-between align-items-center flex-wrap gap-2">

    <div>
        <h3><i class="bi bi-hourglass-split"></i> Paiement en cours</h3>
        <div class="sous-titre">Commande #<?php echo $id_commande; ?></div>
    </div>

    <span class="total-commande">
        <?php echo number_format($paiement["montant"], 0, ',', ' '); ?> FCFA
    </span>

</div>

<div class="p-4 text-center">

    <!-- ÉTAPE 1 : ATTENTE -->
    <div id="etapeAttente">

        <div class="icone-attente mx-auto mb-4">
            <i class="bi bi-hourglass-split"></i>
        </div>

        <h4 class="mb-2">En attente de confirmation</h4>

        <p class="text-muted mb-1">
            Une demande de paiement de
            <strong><?php echo number_format($paiement["montant"], 0, ',', ' '); ?> FCFA</strong>
            a été envoyée sur votre téléphone.
        </p>

        <p class="text-muted">
            Mode de paiement : <strong><?php echo htmlspecialchars($paiement["mode_paiement"] ?? "Non défini"); ?></strong>
        </p>

        <div class="spinner-personnalise mb-3"></div>

        <p class="text-muted small mb-0">
            <i class="bi bi-info-circle"></i>
            Réf. transaction : <?php echo htmlspecialchars($paiement["reference_transaction"] ?? "N/A"); ?>
        </p>

        <p class="text-muted small">
            Composez votre code secret pour confirmer la transaction...
        </p>

    </div>

    <!-- ÉTAPE 2 : SUCCÈS -->
    <div id="etapeSucces" class="d-none">

        <div class="icone-succes mx-auto mb-4">
            <i class="bi bi-check-circle-fill"></i>
        </div>

        <h4 class="mb-2 text-success">Paiement confirmé !</h4>

        <p class="text-muted">
            Votre paiement a été validé avec succès.
            <br>
            <small>Redirection en cours...</small>
        </p>

        <div class="spinner-personnalise mb-3" style="border-top-color: #1f7a45;"></div>

    </div>

    <!-- Boutons d'action (cachés pendant l'attente) -->
    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4 no-print" id="boutonsAction">

        <a href="../client/mes_commandes.php" class="btn btn-bordeaux">
            <i class="bi bi-receipt"></i>
            Mes commandes
        </a>

        <a href="../client/accueil_client.php" class="btn btn-outline-noir">
            <i class="bi bi-arrow-left"></i>
            Continuer mes achats
        </a>

    </div>

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

//===========================================================
// Gestion de l'attente et redirection automatique
//===========================================================

(function() {

    var etapeAttente = document.getElementById('etapeAttente');
    var etapeSucces = document.getElementById('etapeSucces');
    var boutonsAction = document.getElementById('boutonsAction');
    var idCommande = <?php echo (int) $id_commande; ?>;

    // Cacher les boutons au début
    if (boutonsAction) {
        boutonsAction.style.display = 'none';
    }

    // Attendre 3 secondes (simulation de la saisie du code secret côté client),
    // PUIS valider réellement le paiement côté serveur avant d'afficher le succès.
    setTimeout(function() {

        fetch('valider_paiement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_commande=' + encodeURIComponent(idCommande)
        })
        .then(function(reponse) { return reponse.json(); })
        .then(function(donnees) {

            if (!donnees.succes) {
                // En cas d'échec, on garde l'écran d'attente et on prévient l'utilisateur
                if (etapeAttente) {
                    etapeAttente.innerHTML = '<p class="text-danger fw-semibold">Une erreur est survenue lors de la validation du paiement. Merci de réessayer ou de contacter le support.</p>';
                }
                return;
            }

            if (etapeAttente) etapeAttente.classList.add('d-none');
            if (etapeSucces) etapeSucces.classList.remove('d-none');

            // Afficher les boutons après 1.2 secondes
            setTimeout(function() {
                if (boutonsAction) {
                    boutonsAction.style.display = 'flex';
                }
            }, 1200);

            // Rediriger vers confirmation après 3 secondes
            setTimeout(function() {
                window.location.href = "confirmation_commande.php?id_commande=" + idCommande;
            }, 3000);

        })
        .catch(function() {
            if (etapeAttente) {
                etapeAttente.innerHTML = '<p class="text-danger fw-semibold">Connexion impossible. Merci de réessayer.</p>';
            }
        });

    }, 3000);

})();

</script>

</body>

</html>