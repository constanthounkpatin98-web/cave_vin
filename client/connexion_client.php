<?php

session_start();

require_once("../connexion.php");

//===============================================
// Traitement de la connexion
//===============================================

if(isset($_POST["email"]))
{
    $email        = trim($_POST["email"]);
    $mot_de_passe = $_POST["mot_de_passe"];

    if(empty($email) || empty($mot_de_passe))
    {
        header("Location: connexion_client.php?erreur=champs");
        exit();
    }

    $requete = $connexion->prepare("SELECT * FROM client WHERE email = ?");
    $requete->execute([$email]);
    $client = $requete->fetch();

    if(!$client || !password_verify($mot_de_passe, $client["mot_de_passe"]))
    {
        header("Location: connexion_client.php?erreur=identifiants");
        exit();
    }

    if($client["statut"] != "Actif")
    {
        header("Location: connexion_client.php?erreur=bloque");
        exit();
    }

    $_SESSION["client_id"]  = $client["id_client"];
    $_SESSION["client_nom"] = $client["nom"] . " " . $client["prenom"];

    // Redirection après connexion
    $redirect = isset($_GET['redirect']) && $_GET['redirect'] === 'commande' ? 'valider_panier.php' : 'accueil_client.php';
    header("Location: " . $redirect);
    exit();
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Connexion</title>

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


    /* =====================================================
       SECTION CONNEXION
    ===================================================== */

    .section-auth {

        background:
            radial-gradient(ellipse at 42% 62%, rgba(150,150,150,.55), transparent 60%),
            linear-gradient(135deg, #262626 0%, #4a4a4a 42%, #6e6e6e 65%, #333333 100%);

        padding: 70px 0;

        position: relative;

        overflow: hidden;

    }


    .section-auth::after {

        content: "🍷";
        position: absolute;
        left: -3%;
        bottom: -8%;
        font-size: 14rem;
        opacity: .08;
        transform: rotate(-12deg);
        color: #fff;

    }


    .carte-auth {

        background: #fff;
        border-radius: 16px;
        box-shadow: 0 25px 60px rgba(0,0,0,.35);
        overflow: hidden;
        position: relative;
        z-index: 2;

    }


    .entete-auth {

        background: var(--noir);
        color: #fff;
        padding: 28px 30px;
        text-align: center;
        border-bottom: 3px solid var(--bordeaux);

    }


    .entete-auth .icone {

        font-size: 2rem;
        margin-bottom: 6px;

    }


    .entete-auth h3 {

        margin: 0;
        font-weight: 700;

    }


    .entete-auth p {

        color: #cfc8c2;
        font-size: .85rem;
        margin: 4px 0 0;

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


    .lien-bordeaux {

        color: var(--bordeaux);
        font-weight: 600;
        font-size: .9rem;

    }


    .lien-bordeaux:hover {

        color: var(--bordeaux-fonce);
        text-decoration: underline;

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
    .pied-cave,
    .section-auth {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }


    @media (max-width: 767.98px) {

        .container {
            padding-left: 12px;
            padding-right: 12px;
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

        .entete .d-flex.align-items-center.gap-4 {
            gap: 10px !important;
            font-size: .82rem;
        }

        /* ---------- Section connexion ---------- */

        .section-auth {
            padding: 40px 0;
        }

        .section-auth::after {
            font-size: 9rem;
        }

        .entete-auth {
            padding: 22px 18px;
        }

        .entete-auth h3 {
            font-size: 1.3rem;
        }

        .carte-auth .p-4 {
            padding: 18px !important;
        }

        .carte-auth .d-flex.justify-content-between.align-items-center.mt-4 {
            flex-direction: column;
            align-items: stretch !important;
            gap: 12px;
        }

        .carte-auth .d-flex.justify-content-between.align-items-center.mt-4 .btn {
            width: 100%;
        }

        .carte-auth .lien-bordeaux {
            text-align: center;
            font-size: .85rem;
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

        .entete-auth h3 {
            font-size: 1.15rem;
        }

        .entete .d-flex.align-items-center.gap-4 {
            font-size: .76rem;
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


        <div class="d-flex align-items-center gap-4 flex-wrap">

            <a href="accueil_client.php">
                <i class="bi bi-shop"></i>
                Retour à la boutique
            </a>

            <a href="inscription.php">
                <i class="bi bi-person-plus"></i>
                Créer un compte
            </a>

        </div>

    </div>

</div>


<!-- =====================================================
     SECTION CONNEXION
===================================================== -->

<div class="section-auth">

<div class="container position-relative" style="z-index:2;">

<div class="row justify-content-center">

<div class="col-lg-5 col-md-7">

<div class="carte-auth">

<div class="entete-auth">

<div class="icone">🍷</div>

<h3>Connexion</h3>

<p>Accédez à votre espace client</p>

</div>

<div class="p-4">

<?php if(isset($_GET['erreur']) && $_GET['erreur'] == 'champs'): ?>

<div class="alert alert-danger">Veuillez remplir tous les champs.</div>

<?php elseif(isset($_GET['erreur']) && $_GET['erreur'] == 'identifiants'): ?>

<div class="alert alert-danger">Email ou mot de passe incorrect.</div>

<?php elseif(isset($_GET['erreur']) && $_GET['erreur'] == 'bloque'): ?>

<div class="alert alert-danger">Votre compte est inactif ou bloqué.</div>

<?php endif; ?>

<form action="connexion_client.php<?php echo isset($_GET['redirect']) && $_GET['redirect'] === 'commande' ? '?redirect=commande' : ''; ?>" method="POST">

<div class="mb-3">

<label class="form-label">Email</label>

<input type="email" name="email" class="form-control" required>

</div>

<div class="mb-3">

<label class="form-label">Mot de passe</label>

<input type="password" name="mot_de_passe" class="form-control" required>

</div>

<div class="d-flex justify-content-between align-items-center mt-4">

<a href="inscription.php" class="lien-bordeaux">Pas encore de compte ? S'inscrire</a>

<button type="submit" class="btn btn-bordeaux">Se connecter</button>

</div>

</form>

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


            <div class="col-md-4">

                <h6>Compte</h6>

                <ul class="list-unstyled">
                    <li class="mb-2"><a href="connexion_client.php">Connexion</a></li>
                    <li class="mb-2"><a href="inscription.php">Créer un compte</a></li>
                    <li class="mb-2"><a href="accueil_client.php">Boutique</a></li>
                </ul>

            </div>


            <div class="col-md-4">

                <h6>Aide</h6>

                <ul class="list-unstyled">
                    <li class="mb-2"><a href="../avis/donner_avis.php">Donner un avis</a></li>
                    <li class="mb-2"><a href="accueil_client.php">Nous contacter</a></li>
                </ul>

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