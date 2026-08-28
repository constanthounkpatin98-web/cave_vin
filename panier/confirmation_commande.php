<?php

session_start();

require_once("../connexion.php");

//===============================================
// Récupération de la commande (publique mais sécurisée)
//===============================================

$id_commande = isset($_GET["id_commande"]) ? (int) $_GET["id_commande"] : 0;

if ($id_commande === 0) {
    header("Location: ../client/accueil_client.php");
    exit();
}

// Si le client est connecté, on vérifie que la commande lui appartient.
// Si non connecté, on ne permet pas l'accès à une commande spécifique.
if (!isset($_SESSION["client_id"])) {
    header("Location: ../client/accueil_client.php");
    exit();
}

$requete_commande = $connexion->prepare("
    SELECT *
    FROM commande
    WHERE id_commande = ? AND id_client = ?
");
$requete_commande->execute([$id_commande, $_SESSION["client_id"]]);
$commande = $requete_commande->fetch();

if (!$commande) {
    header("Location: ../client/accueil_client.php");
    exit();
}

//===============================================
// Récupération des informations complémentaires
//===============================================

$requete_livraison = $connexion->prepare("SELECT * FROM livraison WHERE id_commande = ?");
$requete_livraison->execute([$id_commande]);
$livraison = $requete_livraison->fetch();

$requete_lignes = $connexion->prepare("
    SELECT
        ligne_commande.*,
        vin.nom_vin,
        vin.photo,
        vin.millesime
    FROM ligne_commande
    LEFT JOIN vin ON ligne_commande.id_vin = vin.id_vin
    WHERE ligne_commande.id_commande = ?
");
$requete_lignes->execute([$id_commande]);
$lignes = $requete_lignes->fetchAll();

$requete_paiement = $connexion->prepare("SELECT * FROM paiement WHERE id_commande = ? AND statut = 'Réussi'");
$requete_paiement->execute([$id_commande]);
$deja_payee = (bool) $requete_paiement->fetch();

//===============================================
// Variables pour la navbar
//===============================================

$nombre_panier = isset($_SESSION["panier"]) ? array_sum($_SESSION["panier"]) : 0;

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

    $requete_commandes_total = $connexion->prepare("
        SELECT COUNT(*) AS total
        FROM commande
        WHERE id_client = ?
    ");
    $requete_commandes_total->execute([$_SESSION["client_id"]]);
    $nombre_commandes_client = $requete_commandes_total->fetch()["total"];
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Confirmation de commande</title>

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
       CARTES
    ===================================================== */

    .carte-cave {

        border: 1px solid #efe8de;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);
        background: #fff;

    }


    .entete-carte {

        background: var(--anthracite);
        color: #fff;
        padding: 16px 22px;

    }


    .entete-carte h5 {

        margin: 0;
        font-size: 1.05rem;

    }


    .vignette-article {

        width: 56px;
        height: 56px;
        border-radius: 10px;
        background: var(--creme);
        overflow: hidden;
        border: 1px solid #efe8de;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;

    }


    .vignette-article img {

        width: 100%;
        height: 100%;
        object-fit: cover;

    }


    .icone-succes {

        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: #e9f4ec;
        color: #1f7a45;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;

    }


    .total-recap {

        color: var(--bordeaux);
        font-family: Georgia, 'Times New Roman', serif;

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


    .btn-outline-bordeaux {

        border: 1px solid var(--bordeaux);
        color: var(--bordeaux);
        background: transparent;
        font-weight: 600;

    }


    .btn-outline-bordeaux:hover {

        background: var(--bordeaux);
        color: #fff;

    }


    .badge-livraison {

        background: #efe8de;
        color: #5a534b;
        font-weight: 600;

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

        <span class="actif">Confirmation</span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container py-4 py-md-5">

<div class="d-flex align-items-center gap-3 mb-4">

    <div class="icone-succes">
        <i class="bi bi-check-circle-fill"></i>
    </div>

    <div>
        <h1 class="h3 fw-bold mb-0">Commande #<?php echo $commande["id_commande"]; ?> créée</h1>
        <p class="text-muted mb-0">Vérifiez le récapitulatif avant de procéder au paiement.</p>
    </div>

</div>


<div class="row g-4">

    <div class="col-lg-7">

        <div class="carte-cave mb-4">

            <div class="entete-carte">
                <h5><i class="bi bi-basket"></i> Articles commandés</h5>
            </div>

            <ul class="list-group list-group-flush">

                <?php foreach ($lignes as $ligne):

                    $photo_ligne = !empty($ligne["photo"]) ? "../vin/uploads/".$ligne["photo"] : null;

                ?>

                    <li class="list-group-item d-flex align-items-center gap-3">

                        <div class="vignette-article">

                            <?php if ($photo_ligne): ?>

                                <img src="<?php echo htmlspecialchars($photo_ligne); ?>" alt="">

                            <?php else: ?>

                                <span>🍷</span>

                            <?php endif; ?>

                        </div>

                        <div class="flex-grow-1">

                            <div class="fw-semibold">
                                <?php echo htmlspecialchars($ligne["nom_vin"]); ?>
                                <?php if (!empty($ligne["millesime"])): ?>
                                    <span class="fw-normal text-muted">(<?php echo htmlspecialchars($ligne["millesime"]); ?>)</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-muted small">
                                <?php echo number_format($ligne["prix_unitaire"], 0, ',', ' '); ?> FCFA
                                × <?php echo $ligne["quantite"]; ?>
                            </div>

                        </div>

                        <div class="fw-semibold" style="color:var(--bordeaux);">
                            <?php echo number_format($ligne["sous_total"], 0, ',', ' '); ?> FCFA
                        </div>

                    </li>

                <?php endforeach; ?>

            </ul>

        </div>


        <?php if ($livraison): ?>

            <div class="carte-cave">

                <div class="entete-carte">
                    <h5><i class="bi bi-truck"></i> Livraison</h5>
                </div>

                <div class="p-4">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <div class="text-muted small">Adresse</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($livraison["adresse_livraison"]); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Mode de livraison</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($commande["mode_livraison"]); ?></div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Frais de livraison</div>
                            <div class="fw-semibold"><?php echo number_format($livraison["frais_livraison"], 0, ',', ' '); ?> FCFA</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Statut</div>
                            <span class="badge badge-livraison"><?php echo htmlspecialchars($livraison["statut"]); ?></span>
                        </div>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <div class="col-lg-5">

        <div class="carte-cave sticky-top" style="top:1.5rem;">

            <div class="p-4">

                <h5 class="mb-3">Récapitulatif</h5>

                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Articles</span>
                    <span><?php echo number_format($commande["montant_total"] - ($livraison["frais_livraison"] ?? 0), 0, ',', ' '); ?> FCFA</span>
                </div>

                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Livraison</span>
                    <span><?php echo number_format($livraison["frais_livraison"] ?? 0, 0, ',', ' '); ?> FCFA</span>
                </div>

                <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2">
                    <span>Total</span>
                    <span class="total-recap"><?php echo number_format($commande["montant_total"], 0, ',', ' '); ?> FCFA</span>
                </div>


                <?php if ($deja_payee): ?>

                    <div class="alert alert-success mt-3 mb-0">
                        <i class="bi bi-check-circle"></i>
                        Cette commande a déjà été payée.
                    </div>

                    <a href="../livraison/suivi_livraison.php?id_commande=<?php echo $commande["id_commande"]; ?>" class="btn btn-outline-bordeaux w-100 mt-3">
                        Suivre ma livraison
                    </a>

                <?php else: ?>

                    <a href="../paiement/paiement.php?id_commande=<?php echo $commande["id_commande"]; ?>" class="btn btn-bordeaux w-100 mt-3">
                        Procéder au paiement
                        <i class="bi bi-arrow-right"></i>
                    </a>

                <?php endif; ?>

                <a href="../panier/panier.php" class="btn btn-link w-100 mt-2 text-decoration-none" style="color:var(--bordeaux);">
                    Modifier ma commande
                </a>

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