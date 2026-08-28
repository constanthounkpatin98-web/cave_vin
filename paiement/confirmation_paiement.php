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
// Récupération de la commande + du paiement
//===============================================

$id_commande = isset($_GET["id_commande"]) ? (int) $_GET["id_commande"] : 0;

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

$requete_paiement = $connexion->prepare("

    SELECT *
    FROM paiement
    WHERE id_commande = ?
    ORDER BY id_paiement DESC
    LIMIT 1

");

$requete_paiement->execute([$id_commande]);

$paiement = $requete_paiement->fetch();

if (!$paiement || $paiement["statut"] !== "Validé") {
    header("Location: ../panier/panier.php");
    exit();
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Paiement confirmé</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

</head>

<body class="bg-light">

<div class="container py-5">

<div class="row justify-content-center">

<div class="col-lg-6">

<div class="card shadow-sm">

<div class="card-body text-center p-4 p-md-5">

<div class="text-success mb-3">
    <i class="bi bi-check-circle-fill" style="font-size:4rem;"></i>
</div>

<h2 class="fw-bold mb-2">Paiement réussi !</h2>

<p class="text-muted mb-4">
    Merci pour votre commande, votre paiement a bien été reçu.
</p>

<div class="list-group list-group-flush text-start mb-4">

<div class="list-group-item d-flex justify-content-between">
    <span class="text-muted">Commande</span>
    <span class="fw-semibold">#<?php echo $commande["id_commande"]; ?></span>
</div>

<div class="list-group-item d-flex justify-content-between">
    <span class="text-muted">Référence de transaction</span>
    <span class="fw-semibold"><?php echo htmlspecialchars($paiement["reference_transaction"]); ?></span>
</div>

<div class="list-group-item d-flex justify-content-between">
    <span class="text-muted">Mode de paiement</span>
    <span class="fw-semibold"><?php echo htmlspecialchars($paiement["mode_paiement"]); ?></span>
</div>

<div class="list-group-item d-flex justify-content-between">
    <span class="text-muted">Montant payé</span>
    <span class="fw-semibold"><?php echo number_format($paiement["montant"], 0, ',', ' '); ?> FCFA</span>
</div>

<div class="list-group-item d-flex justify-content-between">
    <span class="text-muted">Date</span>
    <span class="fw-semibold"><?php echo htmlspecialchars($paiement["date_paiement"]); ?></span>
</div>

</div>

<div class="d-flex flex-column flex-md-row gap-2 justify-content-center">

<a href="../livraison/suivi_livraison.php?id_commande=<?php echo $commande["id_commande"]; ?>" class="btn btn-primary">
    <i class="bi bi-truck"></i>
    Suivre ma livraison
</a>

<a href="../client/accueil_client.php" class="btn btn-outline-secondary">
    Continuer mes achats
</a>

</div>

</div>

</div>

</div>

</div>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>