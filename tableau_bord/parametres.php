<?php

session_start();

require_once("../connexion.php");

//===============================================
// Sécurité : administrateur connecté
//===============================================

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}

$id_admin = $_SESSION["admin_id"];

$message_succes = null;
$message_erreur = null;


//===============================================
// Traitement du formulaire "Mon profil"
//===============================================

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'profil')
{
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email'] ?? '');

    if($nom === '' || $prenom === '' || $email === '')
    {
        $message_erreur = "Merci de remplir tous les champs du profil.";
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $message_erreur = "L'adresse email n'est pas valide.";
    }
    else
    {
        try
        {
            // on vérifie que l'email n'est pas déjà utilisé par un autre administrateur
            $req = $connexion->prepare("SELECT id_admin FROM administrateur WHERE email = ? AND id_admin != ?");
            $req->execute([$email, $id_admin]);

            if($req->fetch())
            {
                $message_erreur = "Cette adresse email est déjà utilisée par un autre compte.";
            }
            else
            {
                $req = $connexion->prepare("UPDATE administrateur SET nom = ?, prenom = ?, email = ? WHERE id_admin = ?");
                $req->execute([$nom, $prenom, $email, $id_admin]);

                $_SESSION['admin_nom'] = trim($prenom . ' ' . $nom);
                $message_succes = "Profil mis à jour avec succès.";
            }
        }
        catch(PDOException $e)
        {
            $message_erreur = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}


//===============================================
// Traitement du formulaire "Changer le mot de passe"
//===============================================

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mot_de_passe')
{
    $mdp_actuel  = $_POST['mdp_actuel'] ?? '';
    $mdp_nouveau = $_POST['mdp_nouveau'] ?? '';
    $mdp_confirm = $_POST['mdp_confirm'] ?? '';

    if($mdp_actuel === '' || $mdp_nouveau === '' || $mdp_confirm === '')
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
            $req = $connexion->prepare("SELECT mot_de_passe FROM administrateur WHERE id_admin = ?");
            $req->execute([$id_admin]);
            $ligne = $req->fetch();

            if(!$ligne || !password_verify($mdp_actuel, $ligne['mot_de_passe']))
            {
                $message_erreur = "Le mot de passe actuel est incorrect.";
            }
            else
            {
                $nouveau_hash = password_hash($mdp_nouveau, PASSWORD_DEFAULT);
                $req = $connexion->prepare("UPDATE administrateur SET mot_de_passe = ? WHERE id_admin = ?");
                $req->execute([$nouveau_hash, $id_admin]);
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
// Chargement des infos actuelles de l'administrateur
//===============================================

$admin = [
    'nom' => '', 'prenom' => '', 'email' => '', 'role' => 'Administrateur',
    'statut' => 'Actif', 'date_creation' => null,
];

$erreur_chargement = null;

try
{
    $req = $connexion->prepare("SELECT nom, prenom, email, role, statut, date_creation FROM administrateur WHERE id_admin = ?");
    $req->execute([$id_admin]);
    $ligne = $req->fetch();

    if($ligne)
    {
        $admin = $ligne;
        $_SESSION['admin_nom'] = trim($ligne['prenom'] . ' ' . $ligne['nom']);
    }
}
catch(PDOException $e)
{
    $erreur_chargement = $e->getMessage();
}

$nom_admin = $_SESSION['admin_nom'] ?? 'Admin';


//===============================================
// Notifications (pour la cloche du topbar)
//===============================================

$notifications_non_lues = 0;
$notifications_recentes = [];

try
{
    $notifications_non_lues = $connexion->query("SELECT COUNT(*) AS total FROM notification WHERE statut = 'Non lue'")->fetch()["total"];
    $requete_notif_recentes = $connexion->query("
        SELECT id_notification, titre, message, statut, id_client, date_envoi
        FROM notification
        ORDER BY date_envoi DESC
        LIMIT 8
    ");
    $notifications_recentes = $requete_notif_recentes->fetchAll();
}
catch(PDOException $e) { /* silencieux */ }


//===============================================
// Seuil de stock faible (préférence d'affichage)
// Pas de table dédiée dans la base actuelle : on la stocke en session pour
// cette connexion. Si tu veux qu'elle soit permanente, ajoute une colonne
// (ex. seuil_stock_faible) à la table administrateur et remplace le bloc
// ci-dessous par une lecture/écriture en base, sur le même principe que
// le profil plus haut.
//===============================================

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preferences')
{
    $seuil = (int)($_POST['seuil_stock_faible'] ?? 10);
    $_SESSION['seuil_stock_faible'] = max(1, $seuil);
    $message_succes = "Préférences enregistrées pour cette session.";
}

$seuil_stock_faible = $_SESSION['seuil_stock_faible'] ?? 10;


//===============================================
// Utilitaires d'affichage
//===============================================

function temps_ecoule($date)
{
    if(!$date) return '';
    $diff = time() - strtotime($date);
    if($diff < 60)    return "à l'instant";
    if($diff < 3600)  return floor($diff / 60) . " min";
    if($diff < 86400) return floor($diff / 3600) . " h";
    return date("d/m H:i", strtotime($date));
}

function date_francaise($timestamp)
{
    $jours = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
    $mois  = ['January'=>'janvier','February'=>'février','March'=>'mars','April'=>'avril','May'=>'mai','June'=>'juin','July'=>'juillet','August'=>'août','September'=>'septembre','October'=>'octobre','November'=>'novembre','December'=>'décembre'];
    $jour_en = date("l", $timestamp);
    $mois_en = date("F", $timestamp);
    return $jours[$jour_en] . " " . date("d", $timestamp) . " " . $mois[$mois_en] . " " . date("Y", $timestamp);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Paramètres — Gestion des Vins</title>
<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

:root{
    --navy:        #101a34;
    --navy-light:  #182450;
    --navy-hover:  #1c2a5e;
    --bleu:        #2f6fed;
    --bleu-clair:  #eaf1ff;
    --vert:        #16a34a;
    --jaune:       #f59e0b;
    --rouge:       #ef4444;
    --violet:      #7c3aed;
    --cyan:        #06b6d4;
    --fond:        #f4f6fb;
    --carte:       #ffffff;
    --texte:       #1e2333;
    --texte-att:   #6b7280;
    --bordure:     #ecedf3;
    --rayon:       16px;
}

*{ box-sizing:border-box; }

body{
    font-family:'Inter', system-ui, sans-serif;
    background:var(--fond);
    color:var(--texte);
    margin:0;
}

.barre-laterale{
    position:fixed;
    top:0; left:0; bottom:0;
    width:250px;
    background:var(--navy);
    color:#c9d0e8;
    display:flex;
    flex-direction:column;
    z-index:40;
}

.logo-app{
    display:flex;
    align-items:center;
    gap:.7rem;
    padding:1.4rem 1.3rem;
    border-bottom:1px solid rgba(255,255,255,.06);
}

.logo-app .icone-logo{
    width:38px; height:38px;
    border-radius:10px;
    background:linear-gradient(135deg, var(--rouge), #b91c1c);
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem;
    flex-shrink:0;
}

.logo-app .titre-logo{ font-weight:700; font-size:.95rem; color:#fff; line-height:1.1; }
.logo-app .sous-titre-logo{ font-size:.72rem; color:#8891b3; }

.nav-laterale{ flex:1; overflow-y:auto; padding:.9rem .7rem; }

.nav-laterale .lien-nav{
    display:flex;
    align-items:center;
    gap:.75rem;
    padding:.62rem .85rem;
    border-radius:10px;
    color:#aab2d1;
    text-decoration:none;
    font-size:.86rem;
    font-weight:500;
    margin-bottom:.15rem;
    transition:background .15s, color .15s;
}

.nav-laterale .lien-nav i{ font-size:1rem; width:20px; text-align:center; }
.nav-laterale .lien-nav:hover{ background:var(--navy-hover); color:#fff; }
.nav-laterale .lien-nav.actif{ background:var(--bleu); color:#fff; }

.pied-sidebar{
    padding:1rem 1.1rem;
    border-top:1px solid rgba(255,255,255,.06);
    display:flex;
    align-items:center;
    gap:.65rem;
}

.avatar-rond{
    width:34px; height:34px;
    border-radius:50%;
    background:var(--violet);
    color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-weight:600;
    font-size:.85rem;
    flex-shrink:0;
}

.pied-sidebar .nom-admin{ font-size:.82rem; font-weight:600; color:#fff; line-height:1.1; }
.pied-sidebar .role-admin{ font-size:.72rem; color:#8891b3; }

.contenu-principal{ margin-left:250px; min-height:100vh; }

.barre-superieure{
    background:var(--carte);
    border-bottom:1px solid var(--bordure);
    padding:.9rem 1.8rem;
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:30;
}

.barre-superieure .date-jour{ font-size:.85rem; color:var(--texte-att); display:flex; align-items:center; gap:.4rem; }
.barre-superieure .zone-droite{ display:flex; align-items:center; gap:1.2rem; }

.cloche-notif{ position:relative; font-size:1.15rem; color:var(--texte-att); }

.cloche-notif .point-badge{
    position:absolute;
    top:-6px; right:-8px;
    background:var(--rouge);
    color:#fff;
    font-size:.62rem;
    font-weight:700;
    border-radius:50%;
    width:17px; height:17px;
    display:flex; align-items:center; justify-content:center;
}

button.cloche-notif{ background:transparent; border:0; line-height:1; }
.dropdown-toggle-sans-fleche::after{ display:none; }

.menu-notif{
    width:340px;
    max-width:90vw;
    border-radius:12px;
    border:1px solid var(--bordure);
    box-shadow:0 12px 28px rgba(16,24,40,.12);
    overflow:hidden;
}

.entete-menu-notif{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:.8rem 1rem;
    border-bottom:1px solid var(--bordure);
    font-weight:700;
    font-size:.85rem;
}

.entete-menu-notif a{ font-size:.76rem; font-weight:600; color:var(--bleu); text-decoration:none; }

.item-menu-notif{
    display:flex;
    align-items:flex-start;
    gap:.7rem;
    padding:.7rem 1rem;
    text-decoration:none;
    color:var(--texte);
    border-bottom:1px solid var(--bordure);
}

.item-menu-notif:last-child{ border-bottom:none; }
.item-menu-notif:hover{ background:var(--fond); }
.item-menu-notif.item-non-lue{ background:var(--bleu-clair); }

.icone-notif-item{ color:var(--bleu); margin-top:.15rem; flex-shrink:0; }
.titre-notif-item{ font-size:.83rem; font-weight:700; }
.texte-notif-item{ font-size:.83rem; }
.heure-notif-item{ font-size:.72rem; color:var(--texte-att); margin-top:.15rem; }
.item-vide-notif{ padding:1.2rem 1rem; font-size:.83rem; color:var(--texte-att); text-align:center; }

.zone-corps{ padding:1.7rem 1.8rem 2.5rem; }

.entete-page h2{ font-weight:800; margin-bottom:.15rem; }
.entete-page p{ color:var(--texte-att); margin-bottom:0; font-size:.9rem; }

.carte{
    background:var(--carte);
    border-radius:var(--rayon);
    border:1px solid var(--bordure);
    box-shadow:0 1px 2px rgba(16,24,40,.04);
}

.entete-carte{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:1rem 1.25rem;
    border-bottom:1px solid var(--bordure);
}

.entete-carte h6{ font-weight:700; margin:0; font-size:.92rem; }

.corps-carte{ padding:1.25rem; }

/* ---------- Spécifique Paramètres ---------- */

.avatar-grand{
    width:64px; height:64px;
    border-radius:50%;
    background:var(--violet);
    color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-weight:700;
    font-size:1.5rem;
    flex-shrink:0;
}

.ligne-info-compte{
    display:flex;
    justify-content:space-between;
    padding:.6rem 0;
    border-bottom:1px solid var(--bordure);
    font-size:.85rem;
}
.ligne-info-compte:last-child{ border-bottom:none; }
.ligne-info-compte .libelle-info{ color:var(--texte-att); }
.ligne-info-compte .valeur-info{ font-weight:600; }

.form-label{ font-size:.83rem; font-weight:600; color:var(--texte); }

@media (max-width: 991px){
    .barre-laterale{ transform:translateX(-100%); transition:transform .25s ease; }
    .barre-laterale.ouverte{ transform:translateX(0); }
    .contenu-principal{ margin-left:0; }
}

.overlay-sidebar{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.4);
    z-index:39;
}

.overlay-sidebar.actif{ display:block; }

.bouton-retour{
    display:inline-flex;
    align-items:center;
    gap:.4rem;
    font-size:.85rem;
    font-weight:600;
    color:var(--texte-att);
    text-decoration:none;
    padding:.4rem .7rem;
    border-radius:8px;
    transition:background .15s, color .15s;
}

.bouton-retour:hover{
    background:var(--bleu-clair);
    color:var(--bleu);
}

</style>
</head>
<body>

<!-- ================= SIDEBAR ================= -->
<aside class="barre-laterale" id="sidebar">

    <div class="logo-app">
        <div class="icone-logo"><i class="bi bi-cup-straw text-white"></i></div>
        <div>
            <div class="titre-logo">GESTION DES VINS</div>
            <div class="sous-titre-logo">Tableau de bord</div>
        </div>
    </div>

    <nav class="nav-laterale">
        <a href="tableau_bord.php" class="lien-nav"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
        <a href="../tableau_bord/liste_client.php" class="lien-nav"><i class="bi bi-people"></i> Clients</a>
        <a href="../commande/liste_commande.php" class="lien-nav"><i class="bi bi-bag-check"></i> Commandes</a>
        <a href="../paiement/liste_paiement.php" class="lien-nav"><i class="bi bi-credit-card"></i> Paiements</a>
        <a href="../livraison/liste_livraison.php" class="lien-nav"><i class="bi bi-truck"></i> Livraisons</a>
        <a href="../vin/liste_vin.php" class="lien-nav"><i class="bi bi-cup-straw"></i> Vins</a>
        <a href="../categorie/liste_categorie.php" class="lien-nav"><i class="bi bi-tags"></i> Catégories</a>
        <a href="../stock/liste_mouvement.php" class="lien-nav"><i class="bi bi-box-seam"></i> Stock &amp; Mouvements</a>
        <a href="../promotion/liste_promotion.php" class="lien-nav"><i class="bi bi-percent"></i> Promotions</a>
        <a href="../notification/liste_notification.php" class="lien-nav"><i class="bi bi-bell"></i> Notifications</a>
        <a href="../avis/liste_avis.php" class="lien-nav"><i class="bi bi-star"></i> Avis</a>
        <a href="../administrateur/liste_admin.php" class="lien-nav"><i class="bi bi-person-badge"></i> Administrateurs</a>
        <a href="rapports.php" class="lien-nav"><i class="bi bi-file-earmark-bar-graph"></i> Rapports</a>
        <a href="parametres.php" class="lien-nav actif"><i class="bi bi-gear"></i> Paramètres</a>
    </nav>

    <div class="pied-sidebar">
        <div class="avatar-rond"><?php echo strtoupper(substr($nom_admin, 0, 1)); ?></div>
        <div>
            <div class="nom-admin"><?php echo htmlspecialchars($nom_admin); ?></div>
            <div class="role-admin">Administrateur</div>
        </div>
    </div>

</aside>

<div class="overlay-sidebar" id="overlaySidebar"></div>

<!-- ================= CONTENU ================= -->
<div class="contenu-principal">

    <div class="barre-superieure">
        <button class="btn btn-light border-0 d-lg-none" id="btnMenuMobile"><i class="bi bi-list fs-4"></i></button>
        <a href="tableau_bord.php" class="bouton-retour"><i class="bi bi-arrow-left"></i> Retour</a>
        <div class="date-jour"><i class="bi bi-calendar3"></i> <?php echo date_francaise(time()); ?></div>
        <div class="zone-droite">
            <div class="dropdown">
                <button type="button" class="btn btn-light border-0 p-0 cloche-notif dropdown-toggle-sans-fleche" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if($notifications_non_lues > 0): ?>
                        <span class="point-badge"><?php echo min($notifications_non_lues, 99); ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end menu-notif p-0">
                    <div class="entete-menu-notif">
                        <span>Notifications</span>
                        <a href="../notification/liste_notification.php">Tout voir</a>
                    </div>
                    <?php if(empty($notifications_recentes)): ?>
                        <div class="item-vide-notif">Aucune notification pour le moment.</div>
                    <?php else: ?>
                        <?php foreach($notifications_recentes as $notif): ?>
                        <a href="../notification/liste_notification.php?id=<?php echo (int)$notif['id_notification']; ?>"
                           class="item-menu-notif <?php echo ($notif['statut'] ?? '') === 'Non lue' ? 'item-non-lue' : ''; ?>">
                            <i class="bi bi-bell-fill icone-notif-item"></i>
                            <div class="flex-grow-1">
                                <?php if(!empty($notif['titre'])): ?>
                                <div class="titre-notif-item"><?php echo htmlspecialchars($notif['titre']); ?></div>
                                <?php endif; ?>
                                <div class="texte-notif-item"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
                                <div class="heure-notif-item"><?php echo temps_ecoule($notif['date_envoi'] ?? null); ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-rond"><?php echo strtoupper(substr($nom_admin, 0, 1)); ?></div>
                <div class="d-none d-md-block">
                    <div style="font-size:.82rem; font-weight:600;"><?php echo htmlspecialchars($nom_admin); ?></div>
                    <div style="font-size:.72rem; color:var(--texte-att);">Administrateur</div>
                </div>
                <i class="bi bi-chevron-down text-muted small"></i>
            </div>
        </div>
    </div>

    <div class="zone-corps">

        <div class="entete-page mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-gear fs-4 text-primary"></i>
            <div>
                <h2>Paramètres</h2>
                <p>Gère ton compte administrateur et tes préférences</p>
            </div>
        </div>

        <?php if($erreur_chargement): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-octagon-fill mt-1"></i>
            <div>
                <strong>Certaines données n'ont pas pu être chargées.</strong>
                <div class="small text-muted mt-1"><?php echo htmlspecialchars($erreur_chargement); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($message_succes): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($message_succes); ?>
        </div>
        <?php endif; ?>

        <?php if($message_erreur): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($message_erreur); ?>
        </div>
        <?php endif; ?>

        <div class="row g-3">

            <!-- ===== Colonne gauche : profil + mot de passe ===== -->
            <div class="col-lg-8">

                <div class="carte mb-3">
                    <div class="entete-carte">
                        <h6><i class="bi bi-person-circle me-1"></i> Mon profil</h6>
                    </div>
                    <div class="corps-carte">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="avatar-grand"><?php echo strtoupper(substr($nom_admin, 0, 1)); ?></div>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($nom_admin); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($admin['role'] ?? 'Administrateur'); ?></div>
                            </div>
                        </div>

                        <form method="post" novalidate>
                            <input type="hidden" name="action" value="profil">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Prénom</label>
                                    <input type="text" name="prenom" class="form-control" required
                                           value="<?php echo htmlspecialchars($admin['prenom']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="nom" class="form-control" required
                                           value="<?php echo htmlspecialchars($admin['nom']); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Adresse email</label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?php echo htmlspecialchars($admin['email']); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="bi bi-check-lg me-1"></i> Enregistrer le profil
                            </button>
                        </form>
                    </div>
                </div>

                <div class="carte mb-3">
                    <div class="entete-carte">
                        <h6><i class="bi bi-shield-lock me-1"></i> Changer le mot de passe</h6>
                    </div>
                    <div class="corps-carte">
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
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="bi bi-key me-1"></i> Modifier le mot de passe
                            </button>
                        </form>
                    </div>
                </div>

                <div class="carte">
                    <div class="entete-carte">
                        <h6><i class="bi bi-sliders me-1"></i> Préférences du tableau de bord</h6>
                    </div>
                    <div class="corps-carte">
                        <form method="post" class="row g-3 align-items-end" novalidate>
                            <input type="hidden" name="action" value="preferences">
                            <div class="col-md-6">
                                <label class="form-label">Seuil de stock faible (bouteilles)</label>
                                <input type="number" name="seuil_stock_faible" class="form-control" min="1"
                                       value="<?php echo (int)$seuil_stock_faible; ?>">
                                <div class="form-text">Utilisé pour l'alerte "Stock faible" du tableau de bord (valable pour cette session).</div>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-check-lg me-1"></i> Enregistrer la préférence
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- ===== Colonne droite : infos du compte + liens rapides ===== -->
            <div class="col-lg-4">

                <div class="carte mb-3">
                    <div class="entete-carte">
                        <h6><i class="bi bi-info-circle me-1"></i> Informations du compte</h6>
                    </div>
                    <div class="corps-carte">
                        <div class="ligne-info-compte">
                            <span class="libelle-info">Rôle</span>
                            <span class="valeur-info"><?php echo htmlspecialchars($admin['role'] ?? '—'); ?></span>
                        </div>
                        <div class="ligne-info-compte">
                            <span class="libelle-info">Statut</span>
                            <span class="valeur-info">
                                <span class="badge rounded-pill <?php echo ($admin['statut'] ?? '') === 'Actif' ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($admin['statut'] ?? '—'); ?>
                                </span>
                            </span>
                        </div>
                        <div class="ligne-info-compte">
                            <span class="libelle-info">Membre depuis</span>
                            <span class="valeur-info">
                                <?php echo !empty($admin['date_creation']) ? date('d/m/Y', strtotime($admin['date_creation'])) : '—'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="carte">
                    <div class="entete-carte">
                        <h6><i class="bi bi-link-45deg me-1"></i> Raccourcis</h6>
                    </div>
                    <div class="corps-carte d-grid gap-2">
                        <a href="../administrateur/liste_admin.php" class="btn btn-light border text-start">
                            <i class="bi bi-person-badge me-2"></i> Gérer les administrateurs
                        </a>
                        <a href="../categorie/liste_categorie.php" class="btn btn-light border text-start">
                            <i class="bi bi-tags me-2"></i> Gérer les catégories
                        </a>
                        <a href="../promotion/liste_promotion.php" class="btn btn-light border text-start">
                            <i class="bi bi-percent me-2"></i> Gérer les promotions
                        </a>
                        <a href="rapports.php" class="btn btn-light border text-start">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i> Voir les rapports
                        </a>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>

// ---- Menu mobile (ouverture/fermeture de la sidebar) ----
const btnMenuMobile = document.getElementById('btnMenuMobile');
const sidebar        = document.getElementById('sidebar');
const overlaySidebar  = document.getElementById('overlaySidebar');

function ouvrirMenu(){
    sidebar.classList.add('ouverte');
    overlaySidebar.classList.add('actif');
}

function fermerMenu(){
    sidebar.classList.remove('ouverte');
    overlaySidebar.classList.remove('actif');
}

btnMenuMobile.addEventListener('click', ouvrirMenu);
overlaySidebar.addEventListener('click', fermerMenu);

</script>

</body>
</html>
