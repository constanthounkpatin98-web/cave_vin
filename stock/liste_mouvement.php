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
// Récupération des mouvements (avec infos vin et admin)
//===============================================

$requete = $connexion->query("
    SELECT stock_mouvement.*, vin.nom_vin, administrateur.nom AS nom_admin, administrateur.prenom AS prenom_admin
    FROM stock_mouvement
    INNER JOIN vin ON stock_mouvement.id_vin = vin.id_vin
    LEFT JOIN administrateur ON stock_mouvement.id_admin = administrateur.id_admin
    ORDER BY stock_mouvement.date_mouvement DESC
");


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
<title>Mouvements de Stock</title>
<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

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

.cloche-secoue{
    animation: secousse-cloche .55s ease-in-out;
}

@keyframes secousse-cloche{
    0%, 100% { transform: rotate(0); }
    20%      { transform: rotate(18deg); }
    40%      { transform: rotate(-16deg); }
    60%      { transform: rotate(10deg); }
    80%      { transform: rotate(-6deg); }
}

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
   STYLES POUR LA LISTE (gabarit commun à toutes
   les pages "bandeau bleu" : livraisons, mouvements,
   notifications, avis)
   ============================================ */

.mouvement-container {
    max-width: 1400px;
    margin: 0 auto;
}

.mouvement-card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}

.mouvement-card .card-body {
    padding: 1.5rem;
    background: white;
}

.bandeau-mouvement {
    background: #0d6efd;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: .6rem;
}

.bandeau-mouvement h3 {
    font-weight: 600;
    font-size: 1.25rem;
    margin: 0;
}

.bandeau-mouvement .btn-light {
    background: white;
    color: #0d6efd;
    border: none;
    font-weight: 500;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
}

.bandeau-mouvement .btn-light:hover {
    background: #f0f0f0;
}

.bandeau-mouvement .btn-succes-bandeau {
    background: var(--vert);
    color: white;
    border: none;
    font-weight: 600;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
}

.bandeau-mouvement .btn-succes-bandeau:hover { background:#128a3e; color:#fff; }

/* Tableau */
.mouvement-card .table {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.mouvement-card .table thead th {
    background: #212529;
    color: white;
    border-color: #212529;
    font-weight: 600;
    padding: 0.75rem 0.75rem;
    vertical-align: middle;
}

.mouvement-card .table td {
    padding: 0.75rem 0.75rem;
    vertical-align: middle;
    border-color: #dee2e6;
}

.mouvement-card .table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0,0,0,0.02);
}

.mouvement-card .table-hover tbody tr:hover {
    background-color: rgba(13,110,253,0.05);
}

/* Boutons d'action */
.btn-modifier {
    background-color: #ffc107;
    color: #000;
    border: none;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
}
.btn-modifier:hover { background-color: #e5a800; color: #000; }

.btn-supprimer {
    background-color: #dc3545;
    color: #fff;
    border: none;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
}
.btn-supprimer:hover { background-color: #c82333; color: #fff; }

.btn-publier {
    background-color: #16a34a;
    color: #fff;
    border: none;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
}
.btn-publier:hover { background-color: #128a3e; color: #fff; }

.btn-masquer {
    background-color: #6c757d;
    color: #fff;
    border: none;
    padding: 0.25rem 0.65rem;
    font-size: 0.75rem;
    border-radius: 4px;
    white-space: nowrap;
}
.btn-masquer:hover { background-color: #565e64; color: #fff; }

.cellule-actions {
    display: flex;
    flex-wrap: nowrap;
    gap: .35rem;
    white-space: nowrap;
}

/* colonne actions : jamais de retour à la ligne, même au zoom */
#tableau td:last-child,
#tableau th:last-child{ white-space:nowrap; }

/* Badge statut générique */
.badge-statut {
    padding: 0.35rem 0.8rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 99px;
}

/* DataTables override */
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
}

.dataTables_wrapper .dataTables_length select {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
}

.dataTables_wrapper .dataTables_info {
    font-size: 0.85rem;
    color: #6c757d;
    padding-top: 0.85rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.25rem 0.6rem;
    margin: 0 0.1rem;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    font-size: 0.85rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #0d6efd;
    color: white !important;
    border-color: #0d6efd;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #e9ecef;
    border-color: #dee2e6;
}

/* Alerte */
.alert-custom {
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.25rem;
}

/* Table responsive */
.table-responsive-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
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

@media (max-width: 768px){
    .zone-corps{ padding:1rem; }
    .barre-superieure{ padding:.7rem 1rem; }
    .barre-superieure .date-jour{ display:none; }
    .bandeau-mouvement h3{ font-size:1rem; }
    .bandeau-mouvement .btn-light{ font-size:0.75rem; padding:0.3rem 0.7rem; }
    .mouvement-card .card-body{ padding:0.8rem; }
    .mouvement-card .table{ font-size:0.8rem; }
}

@media (max-width: 576px){
    .zone-corps{ padding:.5rem; }
    .barre-superieure{ padding:.5rem .7rem; flex-wrap:wrap; gap:.3rem; }
    .barre-superieure .zone-droite{ gap:.6rem; }
    .bandeau-mouvement{ flex-direction:column; gap:.5rem; text-align:center; }
    .bandeau-mouvement h3{ font-size:0.9rem; }
    .dataTables_wrapper .dataTables_filter{ float:none; width:100%; margin-bottom:.5rem; }
    .dataTables_wrapper .dataTables_filter input{ width:100%; }
    .dataTables_wrapper .dataTables_length{ float:none; margin-bottom:.5rem; }
    .dataTables_wrapper .dataTables_info{ float:none; text-align:center; }
    .dataTables_wrapper .dataTables_paginate{ float:none; text-align:center; }
    .dt-buttons{ display:flex; flex-wrap:wrap; gap:.3rem; margin-bottom:.5rem; justify-content:center; }
    .dt-buttons .btn{ font-size:.7rem; padding:.15rem .5rem; }
    .btn-modifier, .btn-supprimer, .btn-publier, .btn-masquer{ font-size:0.7rem; padding:0.2rem 0.5rem; }
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
        <a href="../tableau_bord/tableau_bord.php" class="lien-nav"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
        <a href="../tableau_bord/liste_client.php" class="lien-nav"><i class="bi bi-people"></i> Clients</a>
        <a href="../commande/liste_commande.php" class="lien-nav"><i class="bi bi-bag-check"></i> Commandes</a>
        <a href="../paiement/liste_paiement.php" class="lien-nav"><i class="bi bi-credit-card"></i> Paiements</a>
        <a href="../livraison/liste_livraison.php" class="lien-nav"><i class="bi bi-truck"></i> Livraisons</a>
        <a href="../vin/liste_vin.php" class="lien-nav"><i class="bi bi-cup-straw"></i> Vins</a>
        <a href="../categorie/liste_categorie.php" class="lien-nav"><i class="bi bi-tags"></i> Catégories</a>
        <a href="liste_mouvement.php" class="lien-nav actif"><i class="bi bi-box-seam"></i> Stock &amp; Mouvements</a>
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
        <a href="../tableau_bord/tableau_bord.php" class="bouton-retour"><i class="bi bi-arrow-left"></i> Retour</a>
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

        <div class="mouvement-container">

            <?php if(isset($_GET["ajout"])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Le mouvement de stock a été enregistré avec succès.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php endif; ?>

            <div class="card mouvement-card">

                <div class="bandeau-mouvement">
                    <h3><i class="bi bi-box-seam me-2"></i>Mouvements de Stock</h3>
                    <a href="ajout_mouvement.php" class="btn-succes-bandeau">
                        <i class="bi bi-plus-lg"></i> Nouveau Mouvement
                    </a>
                </div>

                <div class="card-body">
                    <div class="table-responsive-wrapper">
                        <table id="tableau" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Vin</th>
                                    <th>Type</th>
                                    <th>Quantité initiale</th>
                                    <th>Quantité</th>
                                    <th>Stock après</th>
                                    <th>Effectué par</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($ligne = $requete->fetch()): ?>
                                <tr>
                                    <td><?php echo $ligne["id_mouvement"]; ?></td>
                                    <td><?php echo htmlspecialchars($ligne["date_mouvement"]); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($ligne["nom_vin"]); ?></td>
                                    <td>
                                        <?php if($ligne["type_mouvement"] == "Entrée"): ?>
                                        <span class="badge-statut bg-success">Entrée</span>
                                        <?php else: ?>
                                        <span class="badge-statut bg-danger">Sortie</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php
                                        $quantite_initiale = ($ligne["type_mouvement"] == "Entrée")
                                            ? $ligne["stock_apres"] - $ligne["quantite"]
                                            : $ligne["stock_apres"] + $ligne["quantite"];
                                    ?>
                                    <td><?php echo max(0, $quantite_initiale); ?></td>
                                    <td><?php echo $ligne["quantite"]; ?></td>
                                    <td><?php echo $ligne["stock_apres"]; ?></td>
                                    <td><?php echo $ligne["nom_admin"] ? htmlspecialchars($ligne["nom_admin"] . " " . $ligne["prenom_admin"]) : "—"; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>

$(document).ready(function(){
    $('#tableau').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[1,'desc']],
        language: { url: "https://cdn.datatables.net/plug-ins/1.13.8/i18n/fr-FR.json" },
        dom: 'Bfrtip',
        buttons: ['excel','pdf','print']
    });
});

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

<!-- Sonnerie admin (nouveaux paiements / notifications) -->
<script src="../assets/js/sonnerie_admin.js"></script>

</body>
</html>