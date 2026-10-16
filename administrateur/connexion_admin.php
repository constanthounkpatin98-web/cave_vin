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

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="style-connexion-admin.css">

</head>

<body>

<div class="conteneur-connexion-admin">

    <div class="badge-marque-admin">
        <div class="cercle-icone">&#127863;</div>
        <div>
            <div class="nom-marque">La Cave du Bénin</div>
            <div class="sous-titre-marque">Espace Administrateur</div>
        </div>
    </div>

    <div class="carte-connexion-admin">

        <div class="entete-connexion-admin">
            <h3>Connexion</h3>
            <p>Accédez à votre tableau de bord</p>
        </div>

        <div class="corps-connexion-admin">

            <?php if(isset($_GET['erreur']) && $_GET['erreur'] == 'champs'): ?>

            <div class="alert alert-danger">Veuillez remplir tous les champs.</div>

            <?php elseif(isset($_GET['erreur']) && $_GET['erreur'] == 'identifiants'): ?>

            <div class="alert alert-danger">Email ou mot de passe incorrect.</div>

            <?php elseif(isset($_GET['erreur']) && $_GET['erreur'] == 'bloque'): ?>

            <div class="alert alert-danger">Ce compte administrateur est inactif.</div>

            <?php endif; ?>

            <?php if(isset($_GET['reinitialise'])): ?>

            <div class="alert alert-success">Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.</div>

            <?php endif; ?>

            <form action="connexion_admin.php" method="POST">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@lacaveduBénin.com" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Mot de passe</label>
                    <div class="groupe-mot-de-passe-admin">
                        <input type="password" id="champ-mot-de-passe-admin" name="mot_de_passe" class="form-control" placeholder="••••••••" required>
                        <span class="bascule-mot-de-passe-admin" onclick="basculerMotDePasseAdmin()">
                            <i class="bi bi-eye-slash" id="icone-oeil-admin"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn btn-connexion-admin w-100">Se connecter</button>

                <div class="separateur-connexion-admin">ou</div>

                <a href="mot_de_passe_oublie.php" class="lien-mot-de-passe-oublie">&#128273; Mot de passe oublié ?</a>

            </form>

        </div>

    </div>

</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>
function basculerMotDePasseAdmin() {
    const champ = document.getElementById("champ-mot-de-passe-admin");
    const icone = document.getElementById("icone-oeil-admin");

    if (champ.type === "password") {
        champ.type = "text";
        icone.classList.remove("bi-eye-slash");
        icone.classList.add("bi-eye");
    } else {
        champ.type = "password";
        icone.classList.remove("bi-eye");
        icone.classList.add("bi-eye-slash");
    }
}
</script>

</body>

</html>
