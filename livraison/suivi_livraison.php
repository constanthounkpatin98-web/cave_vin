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
// Id de la commande à suivre
//===============================================

$id_commande = isset($_GET["id_commande"]) ? (int) $_GET["id_commande"] : 0;


//===============================================
// Liste des commandes du client (pour le sélecteur)
//===============================================

$requete_commandes = $connexion->prepare("

    SELECT id_commande, date_commande, montant_total

    FROM commande

    WHERE id_client = ?

    ORDER BY date_commande DESC

");

$requete_commandes->execute([$_SESSION["client_id"]]);

$commandes_client = $requete_commandes->fetchAll();


// Si aucune commande sélectionnée, on prend la plus récente

if ($id_commande <= 0 && count($commandes_client) > 0) {
    $id_commande = $commandes_client[0]["id_commande"];
}


//===============================================
// Récupération de la livraison liée à la commande
//===============================================

$livraison = null;

if ($id_commande > 0) {

    $requete_livraison = $connexion->prepare("

        SELECT
            livraison.*,
            commande.id_client,
            commande.date_commande,
            commande.montant_total

        FROM livraison

        LEFT JOIN commande ON livraison.id_commande = commande.id_commande

        WHERE livraison.id_commande = ?

    ");

    $requete_livraison->execute([$id_commande]);

    $livraison = $requete_livraison->fetch();

    // Sécurité : la commande doit appartenir au client connecté

    if ($livraison && $livraison["id_client"] != $_SESSION["client_id"]) {
        $livraison = null;
    }

}


//===============================================
// Étapes du suivi
//===============================================

$etapes = ["En attente", "En préparation", "Expédiée", "En cours", "Livrée"];

$etape_actuelle = $livraison ? array_search($livraison["statut"], $etapes) : false;

if ($etape_actuelle === false) {
    $etape_actuelle = 0;
}

$est_annulee = $livraison && $livraison["statut"] === "Annulée";

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Suivi de livraison</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

</head>

<body class="bg-light">

<div class="container py-4 py-md-5">

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../client/accueil_client.php" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item"><a href="../client/mes_commandes.php" class="text-decoration-none">Mes commandes</a></li>
        <li class="breadcrumb-item active" aria-current="page">Suivi de livraison</li>
    </ol>
</nav>

<h1 class="h3 fw-bold mb-4">Suivi de livraison</h1>


<?php if (count($commandes_client) === 0): ?>

    <div class="card shadow-sm text-center py-5">
        <div class="card-body">
            <i class="bi bi-box-seam display-4 text-muted mb-3 d-block"></i>
            <p class="text-muted mb-3">Vous n'avez pas encore de commande.</p>
            <a href="../client/accueil_client.php" class="btn btn-primary">Voir les vins</a>
        </div>
    </div>

<?php else: ?>

    <!-- SÉLECTEUR DE COMMANDE -->

    <form action="suivi_livraison.php" method="GET" class="row g-2 mb-4">

        <div class="col-md-6">

            <select name="id_commande" class="form-select" onchange="this.form.submit()">

                <?php foreach ($commandes_client as $cmd): ?>

                    <option
                        value="<?php echo $cmd["id_commande"]; ?>"
                        <?php echo $cmd["id_commande"] == $id_commande ? "selected" : ""; ?>
                    >
                        Commande #<?php echo $cmd["id_commande"]; ?>
                        — <?php echo htmlspecialchars($cmd["date_commande"]); ?>
                        — <?php echo number_format($cmd["montant_total"], 0, ',', ' '); ?> FCFA
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    </form>


    <?php if (!$livraison): ?>

        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Aucune information de livraison n'est disponible pour cette commande pour le moment.
        </div>

    <?php else: ?>

        <div class="card shadow-sm mb-4">

            <div class="card-body">

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">

                    <h5 class="mb-0">
                        Commande #<?php echo $livraison["id_commande"]; ?>
                    </h5>

                    <span class="badge text-bg-<?php
                        echo $est_annulee ? "danger" : "primary";
                    ?> fs-6">
                        <?php echo htmlspecialchars($livraison["statut"]); ?>
                    </span>

                </div>


                <?php if ($est_annulee): ?>

                    <div class="alert alert-danger mb-4">
                        <i class="bi bi-x-circle"></i>
                        Cette livraison a été annulée.
                    </div>

                <?php else: ?>

                    <!-- BARRE DE PROGRESSION DES ÉTAPES -->

                    <div class="d-flex justify-content-between position-relative mb-4 px-2">

                        <div class="position-absolute top-50 start-0 end-0 translate-middle-y bg-light" style="height:4px;z-index:0;"></div>

                        <div
                            class="position-absolute top-50 start-0 translate-middle-y bg-primary"
                            style="height:4px;z-index:0;width:<?php echo $etape_actuelle * (100 / (count($etapes) - 1)); ?>%;"
                        ></div>

                        <?php foreach ($etapes as $index => $etape): ?>

                            <div class="d-flex flex-column align-items-center position-relative" style="z-index:1;width:<?php echo 100 / count($etapes); ?>%;">

                                <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo $index <= $etape_actuelle ? "bg-primary text-white" : "bg-light text-muted border"; ?>" style="width:32px;height:32px;">

                                    <?php if ($index < $etape_actuelle): ?>
                                        <i class="bi bi-check-lg"></i>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>

                                </div>

                                <div class="small text-center mt-2 <?php echo $index <= $etape_actuelle ? "fw-semibold" : "text-muted"; ?>" style="max-width:90px;">
                                    <?php echo htmlspecialchars($etape); ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>


                <hr>


                <div class="row g-3">

                    <div class="col-md-6">
                        <div class="text-muted small">Adresse de livraison</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($livraison["adresse_livraison"]); ?></div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Frais de livraison</div>
                        <div class="fw-semibold"><?php echo number_format($livraison["frais_livraison"], 0, ',', ' '); ?> FCFA</div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Date de livraison</div>
                        <div class="fw-semibold">
                            <?php echo !empty($livraison["date_livraison"]) ? htmlspecialchars($livraison["date_livraison"]) : "À confirmer"; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small">Numéro de suivi</div>
                        <div class="fw-semibold">
                            <?php echo !empty($livraison["num_suivi"]) ? htmlspecialchars($livraison["num_suivi"]) : "—"; ?>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>

<?php endif; ?>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
