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
// Id de la livraison à modifier
//===============================================

$id_livraison = isset($_GET["id_livraison"]) ? (int) $_GET["id_livraison"] : 0;

if ($id_livraison <= 0) {
    header("Location: liste_livraison.php");
    exit();
}

//===============================================
// Table de correspondance : statut livraison -> statut commande
//
// Statut livraison         =>  Statut commande
// -------------------------------------------------
// En attente                => En attente
// En préparation             => Validée
// Expédiée / En cours        => En cours
// Livrée                     => Livrée
// Annulée                    => Annulée
//===============================================

function statut_commande_correspondant($statut_livraison)
{
    $correspondance = [
        "En attente"     => "En attente",
        "En préparation" => "Validée",
        "Expédiée"       => "En cours",
        "En cours"       => "En cours",
        "Livrée"         => "Livrée",
        "Annulée"        => "Annulée",
    ];

    return isset($correspondance[$statut_livraison]) ? $correspondance[$statut_livraison] : "En attente";
}

$statuts_livraison_valides = ["En attente", "En préparation", "Expédiée", "En cours", "Livrée", "Annulée"];

//===============================================
// Traitement du formulaire (POST)
//===============================================

$erreurs = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $statut         = trim($_POST["statut"]);
    $date_livraison = $_POST["date_livraison"] !== "" ? $_POST["date_livraison"] : null;
    $num_suivi      = trim($_POST["num_suivi"]) !== "" ? trim($_POST["num_suivi"]) : null;

    if (!in_array($statut, $statuts_livraison_valides, true)) {
        $erreurs[] = "Veuillez choisir un statut de livraison valide.";
    }

    if (empty($erreurs)) {

        try {

            $connexion->beginTransaction();

            // 1. Récupérer l'id_commande lié à cette livraison

            $requete = $connexion->prepare("SELECT id_commande FROM livraison WHERE id_livraison = ?");
            $requete->execute([$id_livraison]);
            $livraison_existante = $requete->fetch();

            if (!$livraison_existante) {
                throw new Exception("Livraison introuvable.");
            }

            $id_commande = $livraison_existante["id_commande"];

            // 2. Mettre à jour la livraison

            $requete_livraison = $connexion->prepare("

                UPDATE livraison
                SET statut = ?,
                    date_livraison = ?,
                    num_suivi = ?
                WHERE id_livraison = ?

            ");

            $requete_livraison->execute([
                $statut,
                $date_livraison,
                $num_suivi,
                $id_livraison,
            ]);

            // 3. Synchroniser le statut de la commande liée

            $statut_commande = statut_commande_correspondant($statut);

            $requete_commande = $connexion->prepare("UPDATE commande SET statut = ? WHERE id_commande = ?");
            $requete_commande->execute([$statut_commande, $id_commande]);

            $connexion->commit();

            header("Location: liste_livraison.php?modifier=1");
            exit();

        } catch (Exception $e) {

            $connexion->rollBack();
            $erreurs[] = "Une erreur est survenue lors de la mise à jour. Veuillez réessayer.";

        }
    }
}

//===============================================
// Récupération des infos actuelles (livraison + commande + client)
//===============================================

$requete = $connexion->prepare("

    SELECT
        livraison.*,
        commande.id_client,
        commande.date_commande,
        commande.montant_total,
        commande.statut AS statut_commande,
        client.nom,
        client.prenom

    FROM livraison

    LEFT JOIN commande ON livraison.id_commande = commande.id_commande

    LEFT JOIN client ON commande.id_client = client.id_client

    WHERE livraison.id_livraison = ?

");

$requete->execute([$id_livraison]);
$livraison = $requete->fetch();

if (!$livraison) {
    header("Location: liste_livraison.php");
    exit();
}

// Si on revient d'une erreur POST, on garde les valeurs saisies plutôt que celles en base
$statut_affiche         = isset($_POST["statut"]) ? $_POST["statut"] : $livraison["statut"];
$date_livraison_affiche = isset($_POST["date_livraison"]) ? $_POST["date_livraison"] : $livraison["date_livraison"];
$num_suivi_affiche      = isset($_POST["num_suivi"]) ? $_POST["num_suivi"] : $livraison["num_suivi"];

//===============================================
// Couleur du badge de statut (reprend la fonction de liste_livraison.php)
//===============================================

function couleur_statut_livraison($statut)
{
    switch ($statut) {

        case "Livrée":
            return "success";

        case "En cours":
        case "Expédiée":
            return "primary";

        case "En préparation":
            return "info";

        case "Annulée":
            return "danger";

        default: // En attente
            return "secondary";
    }
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
<title>Modifier la livraison #<?php echo $id_livraison; ?></title>
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
   STYLES POUR LA PAGE DE MODIFICATION LIVRAISON
   ============================================ */

.modifier-livraison-container {
    max-width: 1000px;
    margin: 0 auto;
}

.modifier-livraison-card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}

.modifier-livraison-card .card-header {
    background: #0d6efd;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 8px 8px 0 0;
}

.modifier-livraison-card .card-header h3 {
    font-weight: 600;
    font-size: 1.25rem;
    margin: 0;
}

.modifier-livraison-card .card-body {
    padding: 1.5rem;
    background: white;
}

.modifier-livraison-card .form-label {
    font-weight: 500;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.modifier-livraison-card .form-control,
.modifier-livraison-card .form-select {
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
}

.modifier-livraison-card .form-control:focus,
.modifier-livraison-card .form-select:focus {
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

.btn-enregistrer-synchro {
    background-color: #0d6efd;
    color: #fff;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
}

.btn-enregistrer-synchro:hover {
    background-color: #0b5ed7;
    color: #fff;
}

.alert-custom {
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.25rem;
}

.info-commande {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
}

.info-commande .row {
    margin: 0;
}

.info-commande .col-md-6 {
    padding: 0.25rem 0.5rem;
}

.synchro-alert {
    background: #eaf1ff;
    border: 1px solid #cfe2ff;
    border-radius: 8px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
}

.synchro-alert ul {
    padding-left: 1.2rem;
    margin-top: 0.25rem;
}

.synchro-alert ul li {
    margin-bottom: 0.15rem;
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
        <a href="../livraison/liste_livraison.php" class="lien-nav actif"><i class="bi bi-truck"></i> Livraisons</a>
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
        <a href="liste_livraison.php" class="bouton-retour"><i class="bi bi-arrow-left"></i> Retour</a>
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

        <div class="modifier-livraison-container">

            <!-- ============================================
                 ALERTES
                 ============================================ -->
            <?php if (!empty($erreurs)): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($erreurs as $erreur): ?>
                        <li><?php echo htmlspecialchars($erreur); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php endif; ?>

            <!-- ============================================
                 CARTE PRINCIPALE
                 ============================================ -->
            <div class="card modifier-livraison-card">

                <!-- Bandeau bleu -->
                <div class="card-header">
                    <h3><i class="bi bi-truck me-2"></i>Modifier la livraison #<?php echo $id_livraison; ?></h3>
                </div>

                <!-- Corps avec le formulaire -->
                <div class="card-body">

                    <!-- Infos commande / client (lecture seule) -->
                    <div class="info-commande">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Commande :</strong> #<?php echo $livraison["id_commande"]; ?></p>
                                <p class="mb-1"><strong>Client :</strong> <?php echo htmlspecialchars(($livraison["prenom"] ?? "") . " " . ($livraison["nom"] ?? "")); ?></p>
                                <p class="mb-1"><strong>Adresse :</strong> <?php echo htmlspecialchars($livraison["adresse_livraison"]); ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Montant total :</strong> <?php echo number_format($livraison["montant_total"], 0, ',', ' '); ?> FCFA</p>
                                <p class="mb-1"><strong>Frais de livraison :</strong> <?php echo number_format($livraison["frais_livraison"], 0, ',', ' '); ?> FCFA</p>
                                <p class="mb-1">
                                    Statut commande actuel :
                                    <span class="badge text-bg-secondary"><?php echo htmlspecialchars($livraison["statut_commande"]); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Rappel de la synchronisation -->
                    <div class="synchro-alert">
                        <i class="bi bi-info-circle"></i> Le statut de la commande sera mis à jour automatiquement :
                        <ul>
                            <li>En attente → <strong>En attente</strong></li>
                            <li>En préparation → <strong>Validée</strong></li>
                            <li>Expédiée / En cours → <strong>En cours</strong></li>
                            <li>Livrée → <strong>Livrée</strong></li>
                            <li>Annulée → <strong>Annulée</strong></li>
                        </ul>
                    </div>

                    <form action="modifier_livraison.php?id_livraison=<?php echo $id_livraison; ?>" method="POST">

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">Statut de la livraison</label>
                                <select name="statut" class="form-select" required>
                                    <?php foreach ($statuts_livraison_valides as $statut_option): ?>
                                        <option value="<?php echo $statut_option; ?>" <?php echo $statut_affiche === $statut_option ? "selected" : ""; ?>>
                                            <?php echo $statut_option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Actuel :
                                    <span class="badge text-bg-<?php echo couleur_statut_livraison($livraison["statut"]); ?>">
                                        <?php echo htmlspecialchars($livraison["statut"]); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Date de livraison</label>
                                <input
                                    type="date"
                                    name="date_livraison"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($date_livraison_affiche ?? ""); ?>"
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Numéro de suivi</label>
                                <input
                                    type="text"
                                    name="num_suivi"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($num_suivi_affiche ?? ""); ?>"
                                    placeholder="Ex : BJ-2026-000123"
                                >
                            </div>

                        </div>

                        <div class="d-flex justify-content-between mt-4">

                            <a href="liste_livraison.php" class="btn btn-annuler">
                                Annuler
                            </a>

                            <button type="submit" class="btn btn-enregistrer-synchro">
                                <i class="bi bi-save"></i> Enregistrer et synchroniser la commande
                            </button>

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