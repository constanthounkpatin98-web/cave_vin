<?php

require_once("../connexion.php");

//===============================================
// Traitement de la modification
//===============================================

if(isset($_POST["nom"]))
{
    $id_client = $_POST["id_client"];
    $nom       = trim($_POST["nom"]);
    $prenom    = trim($_POST["prenom"]);
    $telephone = trim($_POST["telephone"]);
    $email     = trim($_POST["email"]);
    $adresse   = trim($_POST["adresse"]);
    $statut    = $_POST["statut"];

    if(empty($nom) || empty($prenom) || empty($email) || empty($statut))
    {
        header("Location: modifier_client.php?id_client=".$id_client."&erreur=1");
        exit();
    }

    $requete = $connexion->prepare("

    UPDATE client

    SET nom = ?, prenom = ?, telephone = ?, email = ?, adresse = ?, statut = ?

    WHERE id_client = ?

    ");

    $requete->execute([$nom, $prenom, $telephone, $email, $adresse, $statut, $id_client]);

    header("Location: liste_client.php?modifier=ok");
    exit();
}


//===============================================
// Récupération du client à modifier
//===============================================

if(!isset($_GET["id_client"]))
{
    header("Location: liste_client.php");
    exit();
}

$id_client = $_GET["id_client"];

$requete = $connexion->prepare("SELECT * FROM client WHERE id_client = ?");
$requete->execute([$id_client]);
$client = $requete->fetch();

if(!$client)
{
    header("Location: liste_client.php");
    exit();
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Modifier le Client</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow">

<div class="card-header bg-warning text-dark">

<h3 class="mb-0">Modifier le client</h3>

</div>

<div class="card-body">

<?php if(isset($_GET['erreur'])): ?>

<div class="alert alert-danger">Veuillez remplir tous les champs obligatoires.</div>

<?php endif; ?>

<form action="modifier_client.php" method="POST">

<input type="hidden" name="id_client" value="<?php echo $client["id_client"]; ?>">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">Nom</label>

<input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($client["nom"]); ?>" required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">Prénom</label>

<input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars($client["prenom"]); ?>" required>

</div>

</div>

<div class="mb-3">

<label class="form-label">Téléphone</label>

<input type="text" name="telephone" class="form-control" value="<?php echo htmlspecialchars($client["telephone"]); ?>" placeholder="EX 01 97 00 00 00">

</div>

<div class="mb-3">

<label class="form-label">Email</label>

<input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($client["email"]); ?>" required>

</div>

<div class="mb-3">

<label class="form-label">Adresse</label>

<input type="text" name="adresse" class="form-control" value="<?php echo htmlspecialchars($client["adresse"]); ?>">

</div>

<div class="mb-3">

<label class="form-label">Statut</label>

<select class="form-select" name="statut" required>

<option value="Actif" <?php echo $client["statut"] == "Actif" ? "selected" : ""; ?>>Actif</option>

<option value="Inactif" <?php echo $client["statut"] == "Inactif" ? "selected" : ""; ?>>Inactif</option>

<option value="Bloqué" <?php echo $client["statut"] == "Bloqué" ? "selected" : ""; ?>>Bloqué</option>

</select>

</div>

<div class="d-flex justify-content-between">

<a href="liste_client.php" class="btn btn-secondary">Annuler</a>

<button type="submit" class="btn btn-warning">Mettre à jour</button>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
