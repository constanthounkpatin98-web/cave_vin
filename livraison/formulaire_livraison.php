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
// Le panier ne doit pas être vide
//===============================================

if (!isset($_SESSION["panier"]) || count($_SESSION["panier"]) === 0) {
    header("Location: ../panier/panier.php");
    exit();
}


//===============================================
// Récupération des articles du panier (résumé)
//===============================================

$ids  = array_keys($_SESSION["panier"]);
$in   = str_repeat("?,", count($ids) - 1) . "?";

$requete = $connexion->prepare("SELECT * FROM vin WHERE id_vin IN ($in)");
$requete->execute($ids);

$articles      = [];
$montant_total = 0;
$nb_articles   = 0;

while ($vin = $requete->fetch()) {

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


//===============================================
// Frais de livraison par mode (indicatif)
//===============================================

$frais_livraison = [
    "Standard" => 1500,
    "Express"  => 3500,
];


//===============================================
// Pré-remplissage à partir du profil client
//===============================================

$requete_client = $connexion->prepare("SELECT * FROM client WHERE id_client = ?");
$requete_client->execute([$_SESSION["client_id"]]);
$client = $requete_client->fetch();


//===============================================
// Erreurs de validation (si redirigé après échec)
//===============================================

$erreurs = isset($_SESSION["erreurs_livraison"]) ? $_SESSION["erreurs_livraison"] : [];
unset($_SESSION["erreurs_livraison"]);

$valeurs = isset($_SESSION["valeurs_livraison"]) ? $_SESSION["valeurs_livraison"] : [];
unset($_SESSION["valeurs_livraison"]);

function valeur_champ($valeurs, $client, $champ, $champ_client = null)
{
    if (isset($valeurs[$champ])) {
        return $valeurs[$champ];
    }

    if ($champ_client && $client && !empty($client[$champ_client])) {
        return $client[$champ_client];
    }

    return "";
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Informations de livraison</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

</head>

<body class="bg-light">

<div class="container py-4 py-md-5">

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../client/accueil_client.php" class="text-decoration-none">Accueil</a></li>
        <li class="breadcrumb-item"><a href="../panier/panier.php" class="text-decoration-none">Panier</a></li>
        <li class="breadcrumb-item active" aria-current="page">Livraison</li>
    </ol>
</nav>

<h1 class="h3 fw-bold mb-4">Informations de livraison</h1>


<?php if (!empty($erreurs)): ?>

    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($erreurs as $erreur): ?>
                <li><?php echo htmlspecialchars($erreur); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

<?php endif; ?>


<div class="row g-4">

    <div class="col-lg-8">

        <div class="card shadow-sm">

            <div class="card-body">

                <form action="enregistrer_livraison.php" method="POST">

                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label">Adresse de livraison</label>
                            <input
                                type="text"
                                name="adresse_livraison"
                                class="form-control"
                                value="<?php echo htmlspecialchars(valeur_champ($valeurs, $client, "adresse_livraison", "adresse")); ?>"
                                placeholder="Ex : Cotonou, quartier Fidjrossè, rue 123"
                                required
                            >
                        </div>

                        <div class="col-12">

                            <label class="form-label d-block">Mode de livraison</label>

                            <?php $mode_selectionne = valeur_champ($valeurs, null, "mode_livraison") ?: "Standard"; ?>

                            <div class="row g-2">

                                <?php foreach ($frais_livraison as $mode => $frais): ?>

                                    <div class="col-md-6">

                                        <label class="border rounded-3 p-3 d-flex align-items-center gap-2 w-100" style="cursor:pointer;">

                                            <input
                                                type="radio"
                                                name="mode_livraison"
                                                value="<?php echo $mode; ?>"
                                                class="form-check-input mt-0"
                                                <?php echo $mode_selectionne === $mode ? "checked" : ""; ?>
                                                required
                                            >

                                            <span class="flex-grow-1">
                                                <span class="d-block fw-semibold"><?php echo $mode; ?></span>
                                                <span class="d-block text-muted small">
                                                    <?php echo $mode === "Express" ? "Livraison en 24h" : "Livraison en 48 à 72h"; ?>
                                                </span>
                                            </span>

                                            <span class="fw-semibold">
                                                <?php echo number_format($frais, 0, ',', ' '); ?> FCFA
                                            </span>

                                        </label>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                            <div class="form-text">
                                Le mode choisi détermine uniquement le montant des frais de livraison enregistré.
                            </div>

                        </div>

                    </div>


                    <div class="d-flex justify-content-between mt-4">

                        <a href="../panier/panier.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i>
                            Retour au panier
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Continuer vers le paiement
                            <i class="bi bi-arrow-right"></i>
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <div class="col-lg-4">

        <div class="card shadow-sm sticky-top" style="top:1.5rem;">

            <div class="card-body">

                <h5 class="mb-3">Résumé de la commande</h5>

                <ul class="list-group list-group-flush mb-3">

                    <?php foreach ($articles as $article): $vin = $article["vin"]; ?>

                        <li class="list-group-item d-flex justify-content-between px-0">

                            <span class="small">
                                <?php echo htmlspecialchars($vin["nom_vin"]); ?>
                                <span class="text-muted">× <?php echo $article["quantite"]; ?></span>
                            </span>

                            <span class="small fw-semibold">
                                <?php echo number_format($article["sous_total"], 0, ',', ' '); ?> FCFA
                            </span>

                        </li>

                    <?php endforeach; ?>

                </ul>

                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Sous-total (<?php echo $nb_articles; ?> article<?php echo $nb_articles > 1 ? "s" : ""; ?>)</span>
                    <span><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</span>
                </div>

                <div class="d-flex justify-content-between small py-1">
                    <span class="text-muted">Livraison</span>
                    <span class="text-muted">Selon mode choisi</span>
                </div>

                <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2">
                    <span>Total estimé</span>
                    <span class="text-primary"><?php echo number_format($montant_total, 0, ',', ' '); ?> FCFA</span>
                </div>

            </div>

        </div>

    </div>

</div>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
