<?php

require_once("../connexion.php");

$requete = $connexion->query("

SELECT avis.*, client.nom AS nom_client, client.prenom AS prenom_client

FROM avis

INNER JOIN client ON avis.id_client = client.id_client

WHERE avis.statut = 'Publié'

ORDER BY avis.date_avis DESC

");

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Avis de nos clients</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<h2 class="mb-4">Avis de nos clients</h2>

<div class="row g-3">

<?php

$aucun_avis = true;

while($ligne = $requete->fetch())
{
    $aucun_avis = false;

?>

<div class="col-md-4">

<div class="card shadow-sm h-100">

<div class="card-body">

<div class="text-warning mb-2">

<?php echo str_repeat("★", $ligne["note"]) . str_repeat("☆", 5 - $ligne["note"]); ?>

</div>

<p class="card-text"><?php echo htmlspecialchars($ligne["commentaire"]); ?></p>

<p class="card-subtitle text-muted small mb-0">

<?php echo htmlspecialchars($ligne["nom_client"]); ?> — <?php echo date("d/m/Y", strtotime($ligne["date_avis"])); ?>

</p>

</div>

</div>

</div>

<?php

}

?>

<?php if($aucun_avis): ?>

<p class="text-muted">Aucun avis pour le moment. Soyez le premier à donner votre avis !</p>

<?php endif; ?>

</div>

<div class="mt-4">

<a href="donner_avis.php" class="btn btn-primary">Laisser un avis</a>

</div>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
