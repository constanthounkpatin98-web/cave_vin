<?php

session_start();

require_once("../connexion.php");

//===============================================
// Sécurité : Rendre public mais sécurisé
//===============================================

$id_commande = isset($_GET["id_commande"]) ? (int) $_GET["id_commande"] : 0;

if (!$id_commande) {
    header("Location: ../client/accueil_client.php");
    exit();
}

// Si le client est connecté, on vérifie que la commande lui appartient
if (isset($_SESSION["client_id"])) {
    $requete_commande = $connexion->prepare("
        SELECT * FROM commande WHERE id_commande = ? AND id_client = ?
    ");
    $requete_commande->execute([$id_commande, $_SESSION["client_id"]]);
    $commande = $requete_commande->fetch();
    
    if (!$commande) {
        header("Location: ../client/accueil_client.php");
        exit();
    }
    
    $id_client = $_SESSION["client_id"];
    
    // Récupérer les infos du client
    $requete_client = $connexion->prepare("SELECT * FROM client WHERE id_client = ?");
    $requete_client->execute([$id_client]);
    $client = $requete_client->fetch();
} else {
    // Si non connecté, on vérifie juste que la commande existe
    $requete_commande = $connexion->prepare("SELECT * FROM commande WHERE id_commande = ?");
    $requete_commande->execute([$id_commande]);
    $commande = $requete_commande->fetch();
    
    if (!$commande) {
        header("Location: ../client/accueil_client.php");
        exit();
    }
    
    $client = null;
}

//===============================================
// Récupération des lignes de commande
//===============================================

$requete_lignes = $connexion->prepare("
    SELECT ligne_commande.*, vin.nom_vin, vin.photo, vin.millesime
    FROM ligne_commande
    LEFT JOIN vin ON ligne_commande.id_vin = vin.id_vin
    WHERE ligne_commande.id_commande = ?
");
$requete_lignes->execute([$id_commande]);
$lignes = $requete_lignes->fetchAll();

//===============================================
// Récupération du paiement
//===============================================

$requete_paiement = $connexion->prepare("SELECT * FROM paiement WHERE id_commande = ?");
$requete_paiement->execute([$id_commande]);
$paiement = $requete_paiement->fetch();

//===============================================
// Récupération de la livraison
//===============================================

$requete_livraison = $connexion->prepare("SELECT * FROM livraison WHERE id_commande = ?");
$requete_livraison->execute([$id_commande]);
$livraison = $requete_livraison->fetch();

//===============================================
// Calcul des totaux
//===============================================

$sous_total = 0;
foreach($lignes as $ligne) {
    $sous_total += $ligne["sous_total"];
}
$frais_livraison = $livraison["frais_livraison"] ?? 0;
$total_general = $sous_total + $frais_livraison;

// Générer un numéro de commande unique
$numero_commande = 'CMD-' . strtoupper(substr(md5($id_commande . date('Ymd')), 0, 8));
$date_commande = date("d/m/Y à H:i", strtotime($commande["date_commande"]));

//===============================================
// Informations pour la navbar
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

// Vérifier le mode de paiement pour les messages spécifiques
$mode_paiement = $paiement["mode_paiement"] ?? "";
$is_livraison = strpos($mode_paiement, 'livraison') !== false || strpos($mode_paiement, 'Livraison') !== false;
$is_virement = strpos($mode_paiement, 'virement') !== false || strpos($mode_paiement, 'Virement') !== false;

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
       STYLE CONFIRMATION COMMANDE (comme l'image)
    ===================================================== */

    .confirmation-container {

        max-width: 650px;
        margin: 0 auto;
        width: 100%;

    }


    .carte-confirmation {

        border: 1px solid #efe8de;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(28,20,20,.05);
        background: #fff;
        min-width: 0;

    }


    .entete-confirmation {

        background: var(--anthracite);
        color: #fff;
        padding: 18px 22px;
        text-align: center;

    }


    .entete-confirmation h3 {

        margin: 0;
        font-size: 1.3rem;
        font-weight: 700;

    }


    .entete-confirmation .sous-titre {

        color: #cfc8c2;
        font-size: .85rem;
        margin-top: 2px;

    }


    .numero-commande {

        text-align: center;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--bordeaux);
        background: #fdf8f5;
        padding: 8px 15px;
        border-radius: 8px;
        display: inline-block;
        margin: 15px 0 10px;

    }


    .infos-client-confirmation {

        background: #f8f5f0;
        border-radius: 8px;
        padding: 15px 20px;
        margin: 15px 0 20px;
        font-size: 0.95rem;
        line-height: 1.7;

    }


    .infos-client-confirmation .label {

        font-weight: 600;
        color: #4a423c;
        display: inline-block;
        min-width: 80px;

    }


    .table-confirmation {

        width: 100%;
        border-collapse: collapse;
        margin: 15px 0 20px;
        font-size: 0.92rem;
        table-layout: fixed;

    }


    .table-confirmation td:first-child,
    .table-confirmation th:first-child {

        overflow-wrap: anywhere;

    }


    .table-confirmation thead th {

        border-bottom: 2px solid var(--bordeaux);
        padding: 10px 8px;
        text-align: left;
        font-weight: 700;
        color: #1c1a19;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;

    }


    .table-confirmation tbody td {

        padding: 10px 8px;
        border-bottom: 1px solid #efe8de;
        vertical-align: middle;

    }


    .table-confirmation tbody tr:last-child td {

        border-bottom: none;

    }


    .table-confirmation .col-qté {

        text-align: center;
        width: 14%;

    }


    .table-confirmation .col-prix {

        text-align: right;
        width: 26%;

    }


    .table-confirmation .col-total {

        text-align: right;
        font-weight: 600;
        width: 26%;

    }


    .totaux-confirmation {

        border-top: 2px solid var(--bordeaux);
        padding-top: 20px;
        margin-top: 10px;

    }


    .totaux-confirmation .ligne-total {

        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.95rem;

    }


    .totaux-confirmation .ligne-total.total-final {

        font-weight: 700;
        font-size: 1.15rem;
        color: var(--bordeaux);
        border-top: 1px solid #efe8de;
        padding-top: 12px;
        margin-top: 6px;

    }


    .message-confirmation {

        background: #f0f7ee;
        border-left: 4px solid #1f7a45;
        padding: 15px 20px;
        border-radius: 6px;
        margin: 20px 0;
        font-size: 0.92rem;
        color: #1f4a2a;

    }


    .message-confirmation i {

        color: #1f7a45;
        margin-right: 8px;

    }


    .message-alerte {

        background: #fbf1de;
        border-left: 4px solid #96721a;
        padding: 15px 20px;
        border-radius: 6px;
        margin: 20px 0;
        font-size: 0.92rem;
        color: #7a5a1a;

    }


    .message-alerte i {

        color: #96721a;
        margin-right: 8px;

    }


    .infos-livraison-confirmation {

        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding: 15px 20px;
        background: #f8f5f0;
        border-radius: 8px;
        margin-top: 15px;
        font-size: 0.9rem;

    }


    .infos-livraison-confirmation .item {

        color: #4a423c;

    }


    .infos-livraison-confirmation .item strong {

        color: #2c2622;

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

        border: 2px solid var(--bordeaux);
        color: var(--bordeaux);
        background: transparent;
        font-weight: 600;

    }


    .btn-outline-bordeaux:hover {

        background: var(--bordeaux);
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


    .actions-confirmation {

        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 25px;
        flex-wrap: wrap;

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


    @media (max-width: 768px) {

        .entete-confirmation h3 {
            font-size: 1.1rem;
        }

        .table-confirmation {
            font-size: 0.8rem;
        }

        .table-confirmation thead th,
        .table-confirmation tbody td {
            padding: 6px 4px;
        }

        .infos-client-confirmation {
            font-size: 0.85rem;
            padding: 12px 15px;
        }

        .infos-client-confirmation .label {
            display: block;
            min-width: 0;
        }

        .totaux-confirmation .ligne-total {
            font-size: 0.85rem;
        }

        .totaux-confirmation .ligne-total.total-final {
            font-size: 1rem;
        }

        .actions-confirmation {
            flex-direction: column;
            align-items: center;
        }

        .actions-confirmation .btn {
            width: 100%;
            text-align: center;
        }

        .infos-livraison-confirmation {
            flex-direction: column;
            gap: 5px;
        }


        /* ---------- Container général ---------- */

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

        .entete .nom-client {
            display: none;
        }

        .entete a[href*="donner_avis"],
        .entete a[href*="mes_commandes"],
        .entete a[href*="deconnexion"],
        .entete a[href*="connexion_client"] {
            font-size: 0;
            line-height: 1;
            white-space: nowrap;
        }

        .entete a[href*="donner_avis"] i,
        .entete a[href*="mes_commandes"] i,
        .entete a[href*="deconnexion"] i,
        .entete a[href*="connexion_client"] i {
            font-size: 1.15rem;
        }

        .entete .d-flex.align-items-center.gap-4 {
            gap: 12px !important;
        }


        /* ---------- Fil d'Ariane ---------- */

        .fil-ariane {
            padding: 10px 0;
            font-size: .78rem;
        }


        /* ---------- Carte confirmation ---------- */

        .entete-confirmation {
            padding: 16px 14px;
        }

        .carte-confirmation .p-4 {
            padding: 18px !important;
        }

        .numero-commande {
            font-size: 1rem;
            padding: 6px 12px;
        }


        /* ---------- Footer : une colonne ---------- */

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
       TRÈS PETITS ÉCRANS : jusqu'à 380px (Galaxy S20 etc.)
    ===================================================== */

    @media (max-width: 380px) {

        .container {
            padding-left: 9px;
            padding-right: 9px;
        }

        .table-confirmation {
            font-size: .72rem;
        }

        .table-confirmation .col-qté {
            width: 16%;
        }

        .table-confirmation .col-prix,
        .table-confirmation .col-total {
            width: 28%;
        }

        .numero-commande {
            font-size: .9rem;
        }

        .infos-livraison-confirmation,
        .infos-client-confirmation {
            font-size: .78rem;
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

            <?php if (isset($_SESSION["client_id"])): ?>
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

                <a href="../client/deconnexion_client.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Déconnexion
                </a>
            <?php else: ?>
                <a href="../client/connexion_client.php">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Connexion / Inscription
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
     CONTENU - CONFIRMATION
===================================================== -->

<div class="container py-4 py-md-5">

    <div class="confirmation-container">

        <div class="carte-confirmation">

            <!-- En-tête -->
            <div class="entete-confirmation">
                <h3>🍷 CAVE À VINS</h3>
                <div class="sous-titre">Votre cave en ligne au Bénin</div>
                <div style="font-size:0.85rem; color:#cfc8c2; margin-top:5px;">
                    <u>Récapitulatif de Commande</u>
                </div>
                <div style="font-size:0.85rem; color:#cfc8c2;">
                    #<?php echo $numero_commande; ?> — <?php echo $date_commande; ?>
                </div>
            </div>

            <div class="p-4">

                <!-- Numéro de commande -->
                <div class="text-center">
                    <div class="numero-commande">
                        #<?php echo $numero_commande; ?>
                    </div>
                </div>

                <!-- Infos client -->
                <?php if ($client): ?>
                <div class="infos-client-confirmation">
                    <div><span class="label">Client :</span> <?php echo htmlspecialchars($client["prenom"] . " " . $client["nom"]); ?></div>
                    <div><span class="label">Téléphone :</span> <?php echo htmlspecialchars($client["telephone"] ?? "Non renseigné"); ?></div>
                    <div><span class="label">Adresse :</span> <?php echo htmlspecialchars($client["adresse"] ?? "Non renseignée"); ?></div>
                </div>
                <?php endif; ?>

                <!-- Tableau des articles -->
                <table class="table-confirmation">
                    <thead>
                        <tr>
                            <th>Articles</th>
                            <th class="col-qté">Qté</th>
                            <th class="col-prix">Prix</th>
                            <th class="col-total">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lignes as $ligne): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ligne["nom_vin"]); ?></td>
                            <td class="col-qté"><?php echo $ligne["quantite"]; ?></td>
                            <td class="col-prix"><?php echo number_format($ligne["prix_unitaire"], 0, ',', ' '); ?> FCFA</td>
                            <td class="col-total"><?php echo number_format($ligne["sous_total"], 0, ',', ' '); ?> FCFA</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Totaux -->
                <div class="totaux-confirmation">
                    <div class="ligne-total">
                        <span>Sous total</span>
                        <span><?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <?php if($frais_livraison > 0): ?>
                    <div class="ligne-total">
                        <span>Frais de livraison</span>
                        <span><?php echo number_format($frais_livraison, 0, ',', ' '); ?> FCFA</span>
                    </div>
                    <?php endif; ?>
                    <div class="ligne-total total-final">
                        <span>Total</span>
                        <span><?php echo number_format($total_general, 0, ',', ' '); ?> FCFA</span>
                    </div>
                </div>

                <!-- Message de confirmation -->
                <?php if ($is_livraison): ?>
                <div class="message-confirmation">
                    <i class="bi bi-check-circle-fill"></i>
                    Nous accusons réception de votre commande, vous recevrez le reçu de cette commande à la livraison !
                </div>
                <?php elseif ($is_virement): ?>
                <div class="message-alerte">
                    <i class="bi bi-info-circle-fill"></i>
                    Nous accusons réception de votre commande. Elle sera traitée après confirmation de votre virement bancaire.
                </div>
                <?php else: ?>
                <div class="message-confirmation">
                    <i class="bi bi-check-circle-fill"></i>
                    Nous accusons réception de votre paiement, vous recevrez le reçu de cette commande à la livraison !
                </div>
                <?php endif; ?>

                <!-- Infos livraison -->
                <div class="infos-livraison-confirmation">
                    <div class="item">
                        <strong>Mode de paiement :</strong>
                        <?php echo htmlspecialchars($paiement["mode_paiement"] ?? "Non défini"); ?>
                    </div>
                    <div class="item">
                        <strong>Mode de livraison :</strong>
                        <?php echo htmlspecialchars($commande["mode_livraison"] ?? "Standard"); ?>
                    </div>
                    <div class="item">
                        <strong>Statut :</strong>
                        <?php echo htmlspecialchars($commande["statut"]); ?>
                    </div>
                </div>

                <!-- Adresse de livraison -->
                <?php if ($livraison): ?>
                <div style="margin-top:15px; font-size:0.85rem; color:#8a8078; text-align:center; border-top:1px solid #efe8de; padding-top:15px;">
                    <div>📦 Livraison à : <?php echo htmlspecialchars($livraison["adresse_livraison"] ?? $client["adresse"] ?? "Non renseignée"); ?></div>
                    <?php if (!empty($livraison["num_suivi"])): ?>
                    <div>📮 Numéro de suivi : <?php echo htmlspecialchars($livraison["num_suivi"]); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Infos supplémentaires -->
                <div style="margin-top:10px; font-size:0.8rem; color:#b5aaa0; text-align:center;">
                    <?php if ($client): ?>
                    📞 +229 <?php echo htmlspecialchars($client["telephone"] ?? "01 95 00 00 00"); ?>
                    <?php endif; ?>
                    <span style="margin:0 8px;">•</span>
                    Merci de votre confiance ! À très bientôt chez Cave à Vins.
                </div>

                <!-- Boutons d'action -->
                <div class="actions-confirmation">

                    <a href="recu_paiement.php?id_commande=<?php echo $id_commande; ?>" class="btn btn-bordeaux" target="_blank">
                        <i class="bi bi-receipt"></i> Voir mon reçu
                    </a>

                    <?php if (isset($_SESSION["client_id"])): ?>
                    <a href="../client/mes_commandes.php" class="btn btn-outline-bordeaux">
                        <i class="bi bi-receipt"></i> Mes commandes
                    </a>
                    <?php endif; ?>

                    <a href="../client/accueil_client.php" class="btn btn-outline-noir">
                        <i class="bi bi-arrow-left"></i> Continuer
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