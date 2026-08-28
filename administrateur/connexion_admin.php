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
        header("Location: connexion_admin.php?erreur=champs");
        exit();
    }

    $requete = $connexion->prepare("SELECT * FROM administrateur WHERE email = ?");
    $requete->execute([$email]);
    $admin = $requete->fetch();

    if(!$admin || !password_verify($mot_de_passe, $admin["mot_de_passe"]))
    {
        header("Location: connexion_admin.php?erreur=identifiants");
        exit();
    }

    if($admin["statut"] != "Actif")
    {
        header("Location: connexion_admin.php?erreur=bloque");
        exit();
    }

    $_SESSION["admin_id"]   = $admin["id_admin"];
    $_SESSION["admin_nom"]  = $admin["nom"] . " " . $admin["prenom"];
    $_SESSION["admin_role"] = $admin["role"];

    header("Location: ../tableau_bord/tableau_bord.php");
    exit();
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Connexion Administrateur</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-lg-5">

<div class="card shadow">

<div class="card-header bg-dark text-white">

<h3 class="mb-0">Espace Administrateur</h3>

</div>

<div class="card-body">

<?php if(isset($_GET['erreur']) && $_GET['erreur'] == 'champs'): ?>

<div class="alert alert-danger">Veuillez remplir tous les champs.</div>

<?php elseif(isset($_GET['erreur']) && $_GET['erreur'] == 'identifiants'): ?>

<div class="alert alert-danger">Email ou mot de passe incorrect.</div>

<?php elseif(isset($_GET['erreur']) && $_GET['erreur'] == 'bloque'): ?>

<div class="alert alert-danger">Ce compte administrateur est inactif.</div>

<?php endif; ?>

<form action="connexion_admin.php" method="POST">

<div class="mb-3">

<label class="form-label">Email</label>

<input type="email" name="email" class="form-control" required>

</div>

<div class="mb-3">

<label class="form-label">Mot de passe</label>

<input type="password" name="mot_de_passe" class="form-control" required>

</div>

<button type="submit" class="btn btn-dark w-100">Se connecter</button>

</form>

</div>

</div>

</div>

</div>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
