<?php

session_start();

require_once("../connexion.php");

//===============================================
// Vérification du panier
//===============================================

if(!isset($_SESSION["panier"]) || count($_SESSION["panier"]) == 0)
{
    header("Location: panier.php");
    exit();
}

//===============================================
// Récupération du récapitulatif du panier
//===============================================

$ids     = array_keys($_SESSION["panier"]);
$in      = str_repeat("?,", count($ids) - 1) . "?";

$requete = $connexion->prepare("SELECT * FROM vin WHERE id_vin IN ($in)");
$requete->execute($ids);

$articles      = [];
$montant_total = 0;

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
}

//===============================================
// Informations d'entête (panier / notifications / commandes)
//===============================================

$nombre_panier = array_sum($_SESSION["panier"]);
$nombre_notifications = 0;
$nombre_commandes_client = 0;
$client_existant = null;
$client_trouve = false;

if(isset($_SESSION["client_id"]))
{
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
else
{
    //===============================================
    // Vérification si l'email existe déjà (via GET pour l'affichage)
    //===============================================
    if(isset($_GET["email"]))
    {
        $email_verif = trim($_GET["email"]);
        if(!empty($email_verif))
        {
            $requete_verif = $connexion->prepare("SELECT * FROM client WHERE email = ?");
            $requete_verif->execute([$email_verif]);
            $client_existant = $requete_verif->fetch();
            if($client_existant)
            {
                $client_trouve = true;
            }
        }
    }
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Valider ma commande</title>

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
       CARTE
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
        padding: 18px 22px;
        border-bottom: 3px solid var(--bordeaux);

    }


    .entete-carte h3 {

        margin: 0;
        font-size: 1.3rem;

    }


    .table-cave thead {

        background: var(--creme);

    }


    .table-cave th {

        font-weight: 700;
        font-size: .85rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6a615a;
        border-bottom: 2px solid #efe8de !important;

    }


    .table-cave td {

        vertical-align: middle;

    }


    .table-cave tfoot th {

        font-family: Georgia, 'Times New Roman', serif;
        color: var(--bordeaux);
        font-size: 1.1rem;

    }


    .form-label {

        font-weight: 600;
        font-size: .88rem;
        color: #4a423c;

    }


    .form-control,
    .form-select {

        border: 1px solid #e6ddd1;
        border-radius: 8px;
        padding: 10px 14px;

    }


    .form-control:focus,
    .form-select:focus {

        border-color: var(--bordeaux);
        box-shadow: 0 0 0 3px rgba(109,22,38,.1);

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


    /* =====================================================
       ALERTE CLIENT EXISTANT
    ===================================================== */

    .alerte-client-existant {

        background: #f0f7ee;
        border-left: 4px solid #1f7a45;
        padding: 15px 20px;
        border-radius: 6px;
        margin: 15px 0 20px;
        font-size: 0.95rem;
        color: #1f4a2a;

    }


    .alerte-client-existant i {

        color: #1f7a45;
        margin-right: 8px;

    }


    .alerte-client-existant .nom-client-connu {

        font-weight: 700;
        color: var(--bordeaux);

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


    /* =====================================================
       TABLETTE : jusqu'à 991px
    ===================================================== */

    @media (max-width: 991.98px) {

        .entete-carte h3 {
            font-size: 1.1rem;
        }

        .table-cave {
            font-size: 0.8rem;
        }

        .table-cave th,
        .table-cave td {
            padding: 6px 4px !important;
        }

        .form-control,
        .form-select {
            font-size: 0.85rem;
            padding: 8px 10px;
        }

        .form-label {
            font-size: 0.8rem;
        }

        .btn {
            font-size: 0.8rem;
            padding: 6px 12px;
        }

        .alerte-client-existant {
            font-size: 0.85rem;
            padding: 12px 15px;
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

        .carte-cave .p-4 {
            padding: 1rem !important;
        }

        .entete-carte {
            padding: 12px 16px;
        }

        .entete-carte h3 {
            font-size: 0.95rem;
        }

        .table-cave {
            font-size: 0.7rem;
        }

        .table-cave tfoot th {
            font-size: 0.8rem !important;
        }

        .row .col-md-6 {
            margin-bottom: 0.5rem;
        }

        form .d-flex.justify-content-between {
            flex-direction: column;
            gap: 10px;
        }

        form .d-flex.justify-content-between .btn {
            width: 100%;
            text-align: center;
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

        .table-cave {
            font-size: 0.65rem;
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


<!-- =====================================================
     FIL D'ARIANE
===================================================== -->

<div class="fil-ariane">

    <div class="container">

        <a href="../client/accueil_client.php">Accueil</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <a href="panier.php">Panier</a>

        <i class="bi bi-chevron-right mx-1"></i>

        <span class="actif">Valider ma commande</span>

    </div>

</div>


<!-- =====================================================
     CONTENU
===================================================== -->

<div class="container py-4 py-md-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="carte-cave">

<div class="entete-carte">

<h3><i class="bi bi-clipboard-check"></i> Finaliser ma commande</h3>

</div>

<div class="p-4">

<h5 class="mb-3">Récapitulatif</h5>

<div class="table-responsive">

<table class="table table-cave align-middle">

<thead>

<tr>

<th>Vin</th>

<th>Quantité</th>

<th>Sous-total</th>

</tr>

</thead>

<tbody>

<?php foreach($articles as $article): ?>

<tr>

<td><?php echo htmlspecialchars($article["vin"]["nom_vin"]); ?></td>

<td><?php echo $article["quantite"]; ?></td>

<td><?php echo number_format($article["sous_total"], 0, ',', ' '); ?> FCFA</td>

</tr>

<?php endforeach; ?>

</tbody>

<tfoot>

<tr>

<th colspan="2" class="text-end">Total</th>

<th><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</th>

</tr>

</tfoot>

</table>

</div>

<?php if(isset($_SESSION["client_id"])): ?>

    <!-- ============================================
         CLIENT CONNECTÉ - Formulaire de validation existant
    ============================================ -->

    <?php
    $requete_client = $connexion->prepare("SELECT * FROM client WHERE id_client = ?");
    $requete_client->execute([$_SESSION["client_id"]]);
    $client = $requete_client->fetch();
    ?>

    <form action="../commande/passer_commande.php" method="POST" class="mt-3">

        <div class="mb-3">
            <label class="form-label">Adresse de livraison</label>
            <input type="text" name="adresse_livraison" class="form-control" value="<?php echo htmlspecialchars($client["adresse"] ?? ""); ?>" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Mode de livraison</label>
            <select class="form-select" name="mode_livraison" required>
                <option value="Standard">Standard</option>
                <option value="Express">Express</option>
                <option value="Retrait en magasin">Retrait en magasin</option>
            </select>
        </div>

        <div class="d-flex justify-content-between">
            <a href="panier.php" class="btn btn-outline-noir">
                <i class="bi bi-arrow-left"></i>
                Retour au panier
            </a>
            <button type="submit" class="btn btn-bordeaux">
                Confirmer la commande
                <i class="bi bi-check2-circle"></i>
            </button>
        </div>

    </form>

<?php else: ?>

    <!-- ============================================
         CLIENT NON CONNECTÉ - Formulaire d'informations
         AVEC VÉRIFICATION CLIENT EXISTANT
    ============================================ -->

    <?php if($client_trouve && $client_existant): ?>

        <!-- ALERTE : CLIENT EXISTANT DÉTECTÉ -->
        <div class="alerte-client-existant">
            <i class="bi bi-person-check-fill"></i>
            Bonjour <span class="nom-client-connu"><?php echo htmlspecialchars($client_existant["prenom"] . " " . $client_existant["nom"]); ?></span> ! 
            Nous vous avons reconnu. 
            <br>
            <small>
                <i class="bi bi-info-circle"></i>
                Vous avez déjà un compte chez nous. 
                <a href="connexion_client.php?redirect=commande" class="fw-bold" style="color:var(--bordeaux);">
                    Connectez-vous
                </a> 
                pour finaliser votre commande plus rapidement.
            </small>
            <br>
            <small class="text-muted">
                Ou continuez en créant un nouveau compte avec cet email (votre compte existant sera utilisé).
            </small>
        </div>

    <?php endif; ?>

    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle"></i>
        Pour finaliser votre commande, veuillez renseigner vos informations. 
        <?php if(!$client_trouve): ?>
            Un compte sera automatiquement créé pour vous.
        <?php else: ?>
            Si vous avez déjà un compte, vous serez connecté automatiquement.
        <?php endif; ?>
    </div>

    <form action="../commande/passer_commande.php" method="POST" class="mt-3" id="formInscription">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Nom <span class="text-danger">*</span></label>
                <input type="text" name="nom" class="form-control" 
                       value="<?php echo $client_existant ? htmlspecialchars($client_existant["nom"]) : ''; ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Prénom <span class="text-danger">*</span></label>
                <input type="text" name="prenom" class="form-control" 
                       value="<?php echo $client_existant ? htmlspecialchars($client_existant["prenom"]) : ''; ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="emailInput" class="form-control" 
                   value="<?php echo $client_existant ? htmlspecialchars($client_existant["email"]) : ''; ?>" 
                   required
                   onblur="verifierEmail(this.value)">
            <div id="emailFeedback" class="form-text"></div>
        </div>

        <div class="mb-3">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" class="form-control" 
                   value="<?php echo $client_existant ? htmlspecialchars($client_existant["telephone"]) : ''; ?>" 
                   placeholder="Ex: 01 97 00 00 00">
        </div>

        <div class="mb-3">
            <label class="form-label">Adresse de livraison <span class="text-danger">*</span></label>
            <input type="text" name="adresse" class="form-control" 
                   value="<?php echo $client_existant ? htmlspecialchars($client_existant["adresse"]) : ''; ?>" required>
        </div>

        <div class="mb-3" id="motDePasseContainer">
            <label class="form-label">Mot de passe <span class="text-danger">*</span></label>
            <input type="password" name="mot_de_passe" id="motDePasse" class="form-control" minlength="8" 
                   <?php echo $client_existant ? '' : 'required'; ?>>
            <small class="text-muted">
                <?php if($client_existant): ?>
                    Laissez vide pour utiliser votre mot de passe existant.
                <?php else: ?>
                    Minimum 8 caractères pour votre compte sécurisé.
                <?php endif; ?>
            </small>
        </div>

        <div class="mb-4">
            <label class="form-label">Mode de livraison</label>
            <select class="form-select" name="mode_livraison" required>
                <option value="Standard">Standard</option>
                <option value="Express">Express</option>
                <option value="Retrait en magasin">Retrait en magasin</option>
            </select>
        </div>

        <div class="d-flex justify-content-between">
            <a href="panier.php" class="btn btn-outline-noir">
                <i class="bi bi-arrow-left"></i>
                Retour au panier
            </a>
            <button type="submit" class="btn btn-bordeaux" id="btnFinaliser">
                Finaliser la commande
                <i class="bi bi-check2-circle"></i>
            </button>
        </div>

    </form>

<?php endif; ?>

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
                    <?php if(isset($_SESSION["client_id"])): ?>
                        <li class="mb-2"><a href="mes_commandes.php">Mes commandes</a></li>
                        <li class="mb-2"><a href="mes_notifications.php">Notifications</a></li>
                        <li class="mb-2"><a href="modifier_client.php">Mon profil</a></li>
                    <?php else: ?>
                        <li class="mb-2"><a href="connexion_client.php">Connexion</a></li>
                        <li class="mb-2"><a href="inscription.php">Créer un compte</a></li>
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
// VÉRIFICATION EMAIL EXISTANT (AJAX)
//===========================================================

function verifierEmail(email) {
    
    var emailInput = document.getElementById('emailInput');
    var feedback = document.getElementById('emailFeedback');
    var btnFinaliser = document.getElementById('btnFinaliser');
    var mdpContainer = document.getElementById('motDePasseContainer');
    var mdpInput = document.getElementById('motDePasse');
    
    if (email.length < 3) {
        feedback.innerHTML = '';
        return;
    }
    
    // Vérification via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'verifier_email.php?email=' + encodeURIComponent(email), true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.existe) {
                feedback.innerHTML = '<span style="color:#1f7a45;"><i class="bi bi-check-circle"></i> Client existant : ' + 
                                     response.nom + ' ' + response.prenom + 
                                     ' ! <a href="connexion_client.php?redirect=commande&email=' + encodeURIComponent(email) + 
                                     '" style="color:#6d1626; font-weight:600;">Se connecter</a></span>';
                // Pré-remplir les champs
                document.querySelector('input[name="nom"]').value = response.nom || '';
                document.querySelector('input[name="prenom"]').value = response.prenom || '';
                document.querySelector('input[name="telephone"]').value = response.telephone || '';
                document.querySelector('input[name="adresse"]').value = response.adresse || '';
                
                // Mot de passe non obligatoire pour les clients existants
                mdpInput.removeAttribute('required');
                mdpInput.placeholder = 'Laissez vide pour utiliser votre mot de passe existant';
                mdpContainer.querySelector('small').textContent = 'Laissez vide pour utiliser votre mot de passe existant.';
                
                btnFinaliser.textContent = 'Finaliser la commande (compte existant)';
            } else {
                feedback.innerHTML = '<span style="color:#6a615a;"><i class="bi bi-person-plus"></i> Nouveau client - un compte sera créé</span>';
                // Réactiver le mot de passe obligatoire
                mdpInput.setAttribute('required', 'required');
                mdpInput.placeholder = '';
                mdpContainer.querySelector('small').textContent = 'Minimum 8 caractères pour votre compte sécurisé.';
                btnFinaliser.textContent = 'Finaliser la commande';
            }
        }
    };
    xhr.send();
}

//===========================================================
// VALIDATION DU FORMULAIRE
//===========================================================

document.getElementById('formInscription').addEventListener('submit', function(e) {
    
    var email = document.getElementById('emailInput').value.trim();
    var mdp = document.getElementById('motDePasse').value;
    
    // Vérifier si l'email est valide
    if (!email || !email.includes('@')) {
        e.preventDefault();
        alert('Veuillez saisir un email valide.');
        document.getElementById('emailInput').focus();
        return;
    }
    
    // Vérifier si un nouveau client a un mot de passe
    var isNewClient = !document.querySelector('#emailFeedback .bi-check-circle');
    if (isNewClient && (!mdp || mdp.length < 8)) {
        e.preventDefault();
        alert('Veuillez saisir un mot de passe d\'au moins 8 caractères.');
        document.getElementById('motDePasse').focus();
        return;
    }
    
});

</script>

</body>

</html>