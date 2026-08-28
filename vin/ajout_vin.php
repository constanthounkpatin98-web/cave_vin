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

$id_admin   = $_SESSION["admin_id"];
$nom_admin  = $_SESSION["admin_nom"] ?? "Admin";

if(!isset($_SESSION["admin_nom"]) || $_SESSION["admin_nom"] === "")
{
    try
    {
        $req_nom_admin = $connexion->prepare("SELECT nom, prenom FROM administrateur WHERE id_admin = ?");
        $req_nom_admin->execute([$id_admin]);
        $ligne_admin = $req_nom_admin->fetch();

        if($ligne_admin)
        {
            $nom_admin = trim(($ligne_admin["prenom"] ?? "") . " " . ($ligne_admin["nom"] ?? ""));
            $_SESSION["admin_nom"] = $nom_admin;
        }
    }
    catch(PDOException $e) { /* on garde "Admin" par défaut */ }
}

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
catch(PDOException $e) { /* silencieux ici */ }

//===============================================
// Traitement de l'ajout
//===============================================

if(isset($_POST["nom_vin"]))
{
    $nom_vin        = trim($_POST["nom_vin"]);
    $millesime      = trim($_POST["millesime"]);
    $pays_origine   = trim($_POST["pays_origine"]);
    $region         = trim($_POST["region"]);
    $couleur        = $_POST["couleur"];
    $degre_alcool   = trim($_POST["degre_alcool"]);
    $prix           = trim($_POST["prix"]);
    $quantite_stock = trim($_POST["quantite_stock"]);
    $description    = trim($_POST["description"]);
    $statut         = $_POST["statut"];
    $id_categorie   = $_POST["id_categorie"];

    if(
        empty($nom_vin) ||
        empty($couleur) ||
        empty($prix) ||
        empty($quantite_stock) ||
        empty($id_categorie)
    )
    {
        header("Location: ajout_vin.php?erreur=1");
        exit();
    }

    // Gestion de l'upload de la photo
    $nom_photo = null;

    if(isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0)
    {
        $dossier_upload = "uploads/";

        if(!is_dir($dossier_upload))
        {
            mkdir($dossier_upload, 0777, true);
        }

        $extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $nom_photo = uniqid("vin_") . "." . $extension;

        move_uploaded_file($_FILES["photo"]["tmp_name"], $dossier_upload . $nom_photo);
    }

    $requete = $connexion->prepare("
        INSERT INTO vin
        (nom_vin, millesime, pays_origine, region, couleur, degre_alcool, prix, quantite_stock, photo, description, statut, id_categorie)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $requete->execute([
        $nom_vin,
        $millesime,
        $pays_origine,
        $region,
        $couleur,
        $degre_alcool,
        $prix,
        $quantite_stock,
        $nom_photo,
        $description,
        $statut,
        $id_categorie,
    ]);

    header("Location: liste_vin.php?ajout=ok");
    exit();
}

//===============================================
// Récupération des catégories groupées par type
//===============================================

$requete_categorie = $connexion->query("SELECT * FROM categorie WHERE statut = 'Actif' ORDER BY type_categorie, libelle ASC");

$categories_groupees = [
    'sucre'      => [],
    'production' => [],
    'gamme'      => [],
];

while($cat = $requete_categorie->fetch())
{
    $categories_groupees[$cat["type_categorie"]][] = $cat;
}

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
<title>Nouveau Vin</title>
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

/* ============================================
   STYLES POUR LA PAGE D'AJOUT
   ============================================ */

.ajout-vin-container {
    max-width: 1000px;
    margin: 0 auto;
}

.ajout-vin-card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}

.ajout-vin-card .card-header {
    background: #0d6efd;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 8px 8px 0 0;
}

.ajout-vin-card .card-header h3 {
    font-weight: 600;
    font-size: 1.25rem;
    margin: 0;
}

.ajout-vin-card .card-body {
    padding: 1.5rem;
    background: white;
}

.ajout-vin-card .form-label {
    font-weight: 500;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.ajout-vin-card .form-control,
.ajout-vin-card .form-select {
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
}

.ajout-vin-card .form-control:focus,
.ajout-vin-card .form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
}

.btn-annuler {
    background-color: #6c757d;
    color: #fff;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
}

.btn-annuler:hover {
    background-color: #5a6268;
    color: #fff;
}

.btn-enregistrer {
    background-color: #198754;
    color: #fff;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
}

.btn-enregistrer:hover {
    background-color: #157347;
    color: #fff;
}

.alert-custom {
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.25rem;
}

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
        <a href="../tableau_bord/tableau_bord.php" class="lien-nav"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
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
        <a href="../tableau_bord/rapports.php" class="lien-nav"><i class="bi bi-file-earmark-bar-graph"></i> Rapports</a>
        <a href="../tableau_bord/parametres.php" class="lien-nav"><i class="bi bi-gear"></i> Paramètres</a>
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

    <!-- Topbar -->
    <div class="barre-superieure">
        <button class="btn btn-light border-0 d-lg-none" id="btnMenuMobile"><i class="bi bi-list fs-4"></i></button>
        <a href="liste_vin.php" class="bouton-retour"><i class="bi bi-arrow-left"></i> Retour</a>
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

    <!-- Zone Corps -->
    <div class="zone-corps">

        <div class="ajout-vin-container">

            <!-- ============================================
                 ALERTES
                 ============================================ -->
            <?php if(isset($_GET['erreur'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Veuillez remplir tous les champs obligatoires.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 CARTE PRINCIPALE
                 ============================================ -->
            <div class="card ajout-vin-card">

                <!-- Bandeau bleu -->
                <div class="card-header">
                    <h3><i class="bi bi-plus-circle me-2"></i>Ajouter un nouveau vin</h3>
                </div>

                <!-- Corps avec le formulaire -->
                <div class="card-body">

                    <form action="ajout_vin.php" method="POST" enctype="multipart/form-data">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nom du vin</label>
                                <input type="text" name="nom_vin" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Millésime</label>
                                <input type="number" name="millesime" class="form-control" min="1900" max="2100" placeholder="EX 2020">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pays d'origine</label>
                                <input type="text" name="pays_origine" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Région</label>
                                <input type="text" name="region" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Couleur</label>
                                <select class="form-select" name="couleur" required>
                                    <option value="">.....Choisir.....</option>
                                    <option value="Rouge">Rouge</option>
                                    <option value="Blanc">Blanc</option>
                                    <option value="Rosé">Rosé</option>
                                    <option value="Effervescent">Effervescent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Degré d'alcool (%)</label>
                                <input type="number" step="0.01" name="degre_alcool" class="form-control" placeholder="EX 13.50">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prix (FCFA)</label>
                                <input type="number" step="0.01" name="prix" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantité en stock</label>
                                <input type="number" name="quantite_stock" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Catégorie</label>
                                <select class="form-select" name="id_categorie" required>
                                    <option value="">.....Choisir.....</option>

                                    <?php if(!empty($categories_groupees['sucre'])): ?>
                                        <optgroup label="Teneur en sucre">
                                            <?php foreach($categories_groupees['sucre'] as $cat): ?>
                                                <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['libelle']); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>

                                    <?php if(!empty($categories_groupees['production'])): ?>
                                        <optgroup label="Mode de production">
                                            <?php foreach($categories_groupees['production'] as $cat): ?>
                                                <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['libelle']); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>

                                    <?php if(!empty($categories_groupees['gamme'])): ?>
                                        <optgroup label="Gamme">
                                            <?php foreach($categories_groupees['gamme'] as $cat): ?>
                                                <option value="<?php echo $cat['id_categorie']; ?>"><?php echo htmlspecialchars($cat['libelle']); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut" required>
                                    <option value="Disponible">Disponible</option>
                                    <option value="Rupture">Rupture</option>
                                    <option value="Archivé">Archivé</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="liste_vin.php" class="btn btn-annuler">Annuler</a>
                            <button type="submit" class="btn btn-enregistrer"><i class="bi bi-save me-1"></i> Enregistrer</button>
                        </div>

                    </form>

                </div>
            </div>

        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>

<script>
// ---- Menu mobile ----
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