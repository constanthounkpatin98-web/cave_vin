<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["panier"]))
{
    $_SESSION["panier"] = [];
}

//===============================================
// Vider le panier (nouvelle action, gérée ici pour
// ne pas créer de fichier supplémentaire)
//===============================================

if(isset($_POST["action"]) && $_POST["action"] == "vider")
{
    $_SESSION["panier"] = [];
    header("Location: panier.php");
    exit();
}

//===============================================
// Récupération des vins présents dans le panier
//===============================================

$articles      = [];
$montant_total = 0;
$nb_articles   = 0;

if(count($_SESSION["panier"]) > 0)
{
    $ids  = array_keys($_SESSION["panier"]);
    $in   = str_repeat("?,", count($ids) - 1) . "?";

    $requete = $connexion->prepare("SELECT * FROM vin WHERE id_vin IN ($in)");
    $requete->execute($ids);

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
        $nb_articles   += $quantite;
    }
}

//===============================================
// Suggestions : quelques vins disponibles qui ne
// sont pas déjà dans le panier
//===============================================

$suggestions = [];

$ids_panier = array_keys($_SESSION["panier"]);

if(count($ids_panier) > 0)
{
    $in_exclu = str_repeat("?,", count($ids_panier) - 1) . "?";
    $requete_sugg = $connexion->prepare("
        SELECT * FROM vin
        WHERE statut = 'Disponible' AND quantite_stock > 0 AND id_vin NOT IN ($in_exclu)
        ORDER BY RAND() LIMIT 6
    ");
    $requete_sugg->execute($ids_panier);
}
else
{
    $requete_sugg = $connexion->prepare("
        SELECT * FROM vin
        WHERE statut = 'Disponible' AND quantite_stock > 0
        ORDER BY RAND() LIMIT 6
    ");
    $requete_sugg->execute();
}

$suggestions = $requete_sugg->fetchAll();

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Mon Panier</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

</head>

<body class="bg-light">

<div class="container py-4 py-md-5">

<!-- FIL D'ARIANE -->

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../client/accueil_client.php" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item active" aria-current="page">Panier</li>
    </ol>
</nav>


<!-- ENTETE -->

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">

    <div class="d-flex align-items-start gap-3">

        <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
            <i class="bi bi-cart3 fs-5"></i>
        </div>

        <div>
            <h1 class="h3 fw-bold mb-1">Panier</h1>
            <p class="text-muted mb-0">Vérifiez vos articles avant de passer la commande.</p>
        </div>

    </div>

    <a href="../client/accueil_client.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
        Continuer mes achats
    </a>

</div>


<?php if(isset($_GET["erreur"]) && $_GET["erreur"] == "indisponible"): ?>

<div class="alert alert-danger">Ce vin n'est plus disponible en quantité suffisante.</div>

<?php elseif(isset($_GET["erreur"])): ?>

<div class="alert alert-danger"><?php echo htmlspecialchars($_GET["erreur"]); ?></div>

<?php endif; ?>


<?php if(count($articles) == 0): ?>

<div class="card shadow-sm text-center py-5">

    <div class="card-body">

        <i class="bi bi-cart-x display-4 text-muted mb-3 d-block"></i>

        <p class="text-muted mb-3">Votre panier est vide.</p>

        <a href="../client/accueil_client.php" class="btn btn-primary">Voir les vins</a>

    </div>

</div>

<?php else: ?>

<div class="row g-4">

<div class="col-lg-8">

<div class="card shadow-sm">

<div class="card-body">

<?php foreach($articles as $article):

    $vin   = $article["vin"];
    $photo = !empty($vin["photo"]) ? "../uploads/".$vin["photo"] : null;

?>

<div class="d-flex align-items-center gap-3 py-3 border-bottom">

<div class="bg-light rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden" style="width:64px;height:64px;">

<?php if($photo): ?>

<img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($vin["nom_vin"]); ?>" class="w-100 h-100" style="object-fit:cover;">

<?php else: ?>

<i class="bi bi-cup-straw text-primary fs-4"></i>

<?php endif; ?>

</div>

<div class="flex-grow-1">

<div class="fw-semibold">
    <?php echo htmlspecialchars($vin["nom_vin"]); ?>
    <?php if(!empty($vin["millesime"])): ?>
        <span class="fw-normal">(<?php echo htmlspecialchars($vin["millesime"]); ?>)</span>
    <?php endif; ?>
</div>

<div class="text-muted small"><?php echo number_format($vin["prix"], 0, ',', ' '); ?> FCFA l'unité</div>

<span class="badge text-bg-success-subtle text-success-emphasis">
    <i class="bi bi-check-circle"></i>
    En stock
</span>

</div>

<form action="modifier_panier.php" method="POST">

<input type="hidden" name="id_vin" value="<?php echo $vin["id_vin"]; ?>">

<div class="input-group input-group-sm" style="width:120px;">

<button type="button" class="btn btn-outline-secondary" onclick="stepQty(this,-1)">−</button>

<input type="number" name="quantite" class="form-control text-center" value="<?php echo $article["quantite"]; ?>" min="1" max="<?php echo $vin["quantite_stock"]; ?>" onchange="this.form.submit()">

<button type="button" class="btn btn-outline-secondary" onclick="stepQty(this,1)">+</button>

</div>

</form>

<div class="fw-semibold text-end" style="min-width:100px;"><?php echo number_format($article["sous_total"], 0, ',', ' '); ?> FCFA</div>

<a href="supprimer_panier.php?id_vin=<?php echo $vin["id_vin"]; ?>" class="btn btn-outline-danger btn-sm" title="Retirer">

<i class="bi bi-trash"></i>

</a>

</div>

<?php endforeach; ?>

<form action="panier.php" method="POST" class="mt-3">

<input type="hidden" name="action" value="vider">

<button type="submit" class="btn btn-link text-danger text-decoration-none p-0 small" onclick="return confirm('Vider tout le panier ?');">

<i class="bi bi-trash3"></i>
Vider le panier

</button>

</form>

</div>

</div>


<?php if(count($suggestions) > 0): ?>

<div class="mt-4">

<h5 class="mb-3">Vous aimerez peut-être</h5>

<div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3">

<?php foreach($suggestions as $sugg): $sphoto = !empty($sugg["photo"]) ? "../uploads/".$sugg["photo"] : null; ?>

<div class="col">

<div class="card h-100 shadow-sm">

<div class="card-body d-flex flex-column p-2">

<div class="bg-light rounded-3 d-flex align-items-center justify-content-center overflow-hidden mb-2" style="aspect-ratio:1/1;">

<?php if($sphoto): ?>

<img src="<?php echo htmlspecialchars($sphoto); ?>" alt="<?php echo htmlspecialchars($sugg["nom_vin"]); ?>" class="w-100 h-100" style="object-fit:cover;">

<?php else: ?>

<i class="bi bi-cup-straw text-primary fs-3"></i>

<?php endif; ?>

</div>

<div class="small fw-semibold" style="min-height:2.3em;"><?php echo htmlspecialchars($sugg["nom_vin"]); ?></div>

<div class="d-flex align-items-center justify-content-between mt-2">

<span class="fw-bold small"><?php echo number_format($sugg["prix"], 0, ',', ' '); ?> FCFA</span>

<form action="ajouter_panier.php" method="POST">

<input type="hidden" name="id_vin" value="<?php echo $sugg["id_vin"]; ?>">

<input type="hidden" name="quantite" value="1">

<button type="submit" class="btn btn-primary btn-sm rounded-circle" title="Ajouter au panier">

<i class="bi bi-plus"></i>

</button>

</form>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<?php endif; ?>

</div>


<div class="col-lg-4">

<div class="card shadow-sm sticky-top mb-3" style="top:1.5rem;">

<div class="card-body">

<h5 class="mb-3">Récapitulatif</h5>

<div class="d-flex justify-content-between small py-1">

<span class="text-muted">Sous-total (<?php echo $nb_articles; ?> article<?php echo $nb_articles > 1 ? "s" : ""; ?>)</span>

<span><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</span>

</div>

<div class="d-flex justify-content-between small py-1">

<span class="text-muted">Livraison</span>

<span class="text-muted">Calculée à l'étape suivante</span>

</div>

<div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2">

<span>Total</span>

<span class="text-primary"><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</span>

</div>

// Dans la section du récapitulatif, remplacer :
<a href="valider_panier.php" class="btn btn-bordeaux w-100 mt-3">
    Passer la commande
    <i class="bi bi-arrow-right"></i>
</a>

</div>

</div>


<div class="card shadow-sm">

<div class="card-body">

<h6 class="mb-3">Nos engagements</h6>

<div class="list-group list-group-flush">

<div class="list-group-item d-flex gap-3 align-items-start px-0">

<div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;">
<i class="bi bi-truck"></i>
</div>

<div>
<div class="fw-semibold small">Livraison rapide</div>
<div class="text-muted small">Livraison en 24 à 48h</div>
</div>

</div>

<div class="list-group-item d-flex gap-3 align-items-start px-0">

<div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;">
<i class="bi bi-shield-check"></i>
</div>

<div>
<div class="fw-semibold small">Paiement sécurisé</div>
<div class="text-muted small">Vos paiements sont protégés</div>
</div>

</div>

<div class="list-group-item d-flex gap-3 align-items-start px-0">

<div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;">
<i class="bi bi-award"></i>
</div>

<div>
<div class="fw-semibold small">Produits authentiques</div>
<div class="text-muted small">Vins 100% authentiques</div>
</div>

</div>

<div class="list-group-item d-flex gap-3 align-items-start px-0">

<div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;">
<i class="bi bi-headset"></i>
</div>

<div>
<div class="fw-semibold small">Service client</div>
<div class="text-muted small">À votre écoute 7j/7</div>
</div>

</div>

</div>

</div>

</div>

</div>

</div>

<?php endif; ?>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>

function stepQty(btn, delta)
{
    var form  = btn.closest("form");
    var input = form.querySelector('input[name="quantite"]');
    var max   = parseInt(input.getAttribute("max"), 10);
    var min   = parseInt(input.getAttribute("min"), 10) || 1;
    var value = parseInt(input.value, 10) || min;

    value += delta;

    if(value < min){ value = min; }
    if(max && value > max){ value = max; }

    input.value = value;
    form.submit();
}

</script>

</body>

</html>
