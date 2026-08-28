<?php

session_start();

//===============================================
// Sécurité : réservé au client connecté
//===============================================

if(!isset($_SESSION["client_id"]))
{
    header("Location: ../client/connexion_client.php");
    exit();
}

require_once("../connexion.php");

$id_client = $_SESSION["client_id"];

$message_succes = null;
$message_erreur = null;


//===============================================
// Traitement du formulaire de modification du profil
//===============================================

if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "modifier_profil")
{
    $nom       = trim($_POST["nom"] ?? "");
    $prenom    = trim($_POST["prenom"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $telephone = trim($_POST["telephone"] ?? "");
    $adresse   = trim($_POST["adresse"] ?? "");

    if($nom === "" || $prenom === "" || $email === "")
    {
        $message_erreur = "Le nom, le prénom et l'email sont obligatoires.";
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $message_erreur = "L'adresse email n'est pas valide.";
    }
    else
    {
        try
        {
            // on vérifie que l'email n'est pas déjà utilisé par un autre client
            $req = $connexion->prepare("SELECT id_client FROM client WHERE email = ? AND id_client != ?");
            $req->execute([$email, $id_client]);

            if($req->fetch())
            {
                $message_erreur = "Cette adresse email est déjà utilisée par un autre compte.";
            }
            else
            {
                $req = $connexion->prepare("
                    UPDATE client
                    SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?
                    WHERE id_client = ?
                ");
                $req->execute([$nom, $prenom, $email, $telephone, $adresse, $id_client]);

                $_SESSION["client_nom"] = trim($prenom . " " . $nom);
                $message_succes = "Ton profil a été mis à jour avec succès.";
            }
        }
        catch(PDOException $e)
        {
            $message_erreur = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}


//===============================================
// Traitement du changement de mot de passe
//===============================================

if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "mot_de_passe")
{
    $mdp_actuel  = $_POST["mdp_actuel"] ?? "";
    $mdp_nouveau = $_POST["mdp_nouveau"] ?? "";
    $mdp_confirm = $_POST["mdp_confirm"] ?? "";

    if($mdp_actuel === "" || $mdp_nouveau === "" || $mdp_confirm === "")
    {
        $message_erreur = "Merci de remplir tous les champs du mot de passe.";
    }
    elseif(strlen($mdp_nouveau) < 8)
    {
        $message_erreur = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    }
    elseif($mdp_nouveau !== $mdp_confirm)
    {
        $message_erreur = "La confirmation ne correspond pas au nouveau mot de passe.";
    }
    else
    {
        try
        {
            $req = $connexion->prepare("SELECT mot_de_passe FROM client WHERE id_client = ?");
            $req->execute([$id_client]);
            $ligne = $req->fetch();

            if(!$ligne || !password_verify($mdp_actuel, $ligne["mot_de_passe"]))
            {
                $message_erreur = "Le mot de passe actuel est incorrect.";
            }
            else
            {
                $nouveau_hash = password_hash($mdp_nouveau, PASSWORD_DEFAULT);
                $req = $connexion->prepare("UPDATE client SET mot_de_passe = ? WHERE id_client = ?");
                $req->execute([$nouveau_hash, $id_client]);
                $message_succes = "Mot de passe modifié avec succès.";
            }
        }
        catch(PDOException $e)
        {
            $message_erreur = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}


//===============================================
// Chargement des infos actuelles du client
//===============================================

$client = [
    "nom" => "", "prenom" => "", "email" => "", "telephone" => "",
    "adresse" => "", "date_inscription" => null, "statut" => "Actif",
];

$erreur_chargement = null;

try
{
    $req = $connexion->prepare("SELECT nom, prenom, email, telephone, adresse, date_inscription, statut FROM client WHERE id_client = ?");
    $req->execute([$id_client]);
    $ligne = $req->fetch();

    if($ligne)
    {
        $client = $ligne;
        $_SESSION["client_nom"] = trim($ligne["prenom"] . " " . $ligne["nom"]);
    }
}
catch(PDOException $e)
{
    $erreur_chargement = $e->getMessage();
}

$nom_client = $_SESSION["client_nom"] ?? "Mon compte";


//===============================================
// Historique des commandes du client (aperçu)
//===============================================

$commandes_client = [];

try
{
    $req = $connexion->prepare("
        SELECT id_commande, date_commande, montant_total, statut
        FROM commande
        WHERE id_client = ?
        ORDER BY date_commande DESC
        LIMIT 5
    ");
    $req->execute([$id_client]);
    $commandes_client = $req->fetchAll();
}
catch(PDOException $e) { /* on n'affiche pas d'erreur bloquante pour cette section secondaire */ }

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mon profil — Cave à Vins</title>
<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css">

<style>
/*
  ATTENTION : cette page est autonome (pas encore raccordée à ton vrai
  header.php / footer.php client). Les couleurs ci-dessous reprennent le
  thème bordeaux / anthracite / or de la boutique. Si tu as déjà des
  variables CSS globales (ex. dans un style.css commun), dis-le-moi et je
  remplace ce bloc par un simple <?php include "../client/header.php"; ?>
  ... <?php include "../client/footer.php"; ?> pour que ce soit 100%
  cohérent avec le reste du site.
*/

:root{
    --bordeaux:       #6d1220;
    --bordeaux-fonce: #4a0c16;
    --anthracite:     #2a2a2e;
    --or:             #c9a227;
    --or-clair:       #e4c96b;
    --fond:           #f7f4ef;
    --carte:          #ffffff;
    --texte:          #241b1d;
    --texte-att:      #7a6f6a;
    --bordure:        #ece4d8;
    --rayon:          14px;
}

*{ box-sizing:border-box; }

body{
    font-family:'Georgia', 'Times New Roman', serif;
    background:var(--fond);
    color:var(--texte);
    margin:0;
}

.entete-boutique{
    background:linear-gradient(135deg, var(--bordeaux), var(--bordeaux-fonce));
    color:#fff;
    padding:1.1rem 2rem;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.entete-boutique .logo-boutique{
    display:flex;
    align-items:center;
    gap:.6rem;
    font-weight:700;
    letter-spacing:.04em;
    font-size:1.05rem;
}

.entete-boutique .logo-boutique i{ color:var(--or-clair); font-size:1.3rem; }

.entete-boutique .lien-retour{
    color:var(--or-clair);
    text-decoration:none;
    font-size:.88rem;
    font-weight:600;
}
.entete-boutique .lien-retour:hover{ color:#fff; }

.conteneur-profil{
    max-width:960px;
    margin:2.2rem auto;
    padding:0 1.2rem 3rem;
}

.entete-page h2{ font-weight:700; margin-bottom:.15rem; color:var(--bordeaux-fonce); }
.entete-page p{ color:var(--texte-att); font-size:.92rem; }

.carte-profil{
    background:var(--carte);
    border-radius:var(--rayon);
    border:1px solid var(--bordure);
    box-shadow:0 2px 10px rgba(74,12,22,.06);
    margin-bottom:1.3rem;
}

.entete-carte-profil{
    display:flex;
    align-items:center;
    gap:.6rem;
    padding:1rem 1.4rem;
    border-bottom:1px solid var(--bordure);
    font-weight:700;
    font-size:.98rem;
    color:var(--bordeaux-fonce);
}

.entete-carte-profil i{ color:var(--or); }

.corps-carte-profil{ padding:1.4rem; }

.avatar-client{
    width:64px; height:64px;
    border-radius:50%;
    background:linear-gradient(135deg, var(--or), #a9821c);
    color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-weight:700;
    font-size:1.5rem;
    flex-shrink:0;
}

.form-label{ font-size:.85rem; font-weight:600; color:var(--anthracite); }

.form-control:focus{
    border-color:var(--or);
    box-shadow:0 0 0 .2rem rgba(201,162,39,.18);
}

.btn-bordeaux{
    background:var(--bordeaux);
    border-color:var(--bordeaux);
    color:#fff;
    font-weight:600;
}
.btn-bordeaux:hover{ background:var(--bordeaux-fonce); border-color:var(--bordeaux-fonce); color:#fff; }

.ligne-commande-profil{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:.65rem 0;
    border-bottom:1px solid var(--bordure);
    font-size:.87rem;
}
.ligne-commande-profil:last-child{ border-bottom:none; }

.pied-boutique{
    text-align:center;
    padding:1.5rem;
    color:var(--texte-att);
    font-size:.82rem;
    border-top:1px solid var(--bordure);
}
</style>
</head>
<body>

<!-- ================= EN-TÊTE ================= -->
<div class="entete-boutique">
    <div class="logo-boutique"><i class="bi bi-cup-straw"></i> CAVE À VINS</div>
    <a href="../client/accueil_client.php" class="lien-retour"><i class="bi bi-arrow-left"></i> Retour à la boutique</a>
</div>

<div class="conteneur-profil">

    <div class="entete-page mb-4">
        <h2>Mon profil</h2>
        <p>Consulte et modifie tes informations personnelles</p>
    </div>

    <?php if($erreur_chargement): ?>
    <div class="alert alert-danger">
        <strong>Certaines données n'ont pas pu être chargées.</strong>
        <div class="small text-muted mt-1"><?php echo htmlspecialchars($erreur_chargement); ?></div>
    </div>
    <?php endif; ?>

    <?php if($message_succes): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message_succes); ?>
    </div>
    <?php endif; ?>

    <?php if($message_erreur): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($message_erreur); ?>
    </div>
    <?php endif; ?>

    <!-- ===== Infos + formulaire de modification ===== -->
    <div class="carte-profil">
        <div class="entete-carte-profil"><i class="bi bi-person-circle"></i> Mes informations</div>
        <div class="corps-carte-profil">

            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="avatar-client"><?php echo strtoupper(substr($nom_client, 0, 1)); ?></div>
                <div>
                    <div class="fw-bold fs-5"><?php echo htmlspecialchars($nom_client); ?></div>
                    <div class="text-muted small">
                        Client depuis le
                        <?php echo !empty($client["date_inscription"]) ? date("d/m/Y", strtotime($client["date_inscription"])) : "—"; ?>
                    </div>
                </div>
            </div>

            <form method="post" novalidate>
                <input type="hidden" name="action" value="modifier_profil">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="prenom" class="form-control" required
                               value="<?php echo htmlspecialchars($client["prenom"]); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input type="text" name="nom" class="form-control" required
                               value="<?php echo htmlspecialchars($client["nom"]); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adresse email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($client["email"]); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" class="form-control"
                               value="<?php echo htmlspecialchars($client["telephone"] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Adresse</label>
                        <textarea name="adresse" class="form-control" rows="2"><?php echo htmlspecialchars($client["adresse"] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-bordeaux mt-3">
                    <i class="bi bi-check-lg me-1"></i> Enregistrer les modifications
                </button>
            </form>

        </div>
    </div>

    <!-- ===== Changer le mot de passe ===== -->
    <div class="carte-profil">
        <div class="entete-carte-profil"><i class="bi bi-shield-lock"></i> Changer mon mot de passe</div>
        <div class="corps-carte-profil">
            <form method="post" novalidate>
                <input type="hidden" name="action" value="mot_de_passe">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="mdp_actuel" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="mdp_nouveau" class="form-control" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="mdp_confirm" class="form-control" minlength="8" required>
                    </div>
                </div>
                <div class="form-text mt-2">Le mot de passe doit contenir au moins 8 caractères.</div>
                <button type="submit" class="btn btn-bordeaux mt-3">
                    <i class="bi bi-key me-1"></i> Modifier le mot de passe
                </button>
            </form>
        </div>
    </div>

    <!-- ===== Aperçu des commandes ===== -->
    <div class="carte-profil">
        <div class="entete-carte-profil"><i class="bi bi-bag-check"></i> Mes dernières commandes</div>
        <div class="corps-carte-profil">
            <?php if(empty($commandes_client)): ?>
            <p class="text-muted small mb-0">Tu n'as pas encore passé de commande.</p>
            <?php else: ?>
                <?php foreach($commandes_client as $cmd): ?>
                <div class="ligne-commande-profil">
                    <span>
                        <strong>CMD-<?php echo str_pad($cmd["id_commande"], 4, "0", STR_PAD_LEFT); ?></strong>
                        — <?php echo date("d/m/Y", strtotime($cmd["date_commande"])); ?>
                    </span>
                    <span>
                        <?php echo number_format($cmd["montant_total"], 0, ',', ' '); ?> F
                        <span class="badge rounded-pill text-bg-secondary ms-2"><?php echo htmlspecialchars($cmd["statut"]); ?></span>
                    </span>
                </div>
                <?php endforeach; ?>
                <a href="../commande/mes_commandes.php" class="d-inline-block mt-3 small" style="color:var(--bordeaux); font-weight:600; text-decoration:none;">
                    Voir toutes mes commandes <i class="bi bi-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ================= PIED DE PAGE ================= -->
<div class="pied-boutique">
    &copy; <?php echo date("Y"); ?> Cave à Vins — Tous droits réservés.
</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>