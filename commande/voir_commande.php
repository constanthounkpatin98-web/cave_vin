<?php

session_start();

//===============================================
// Sécurité : administrateur connecté
//===============================================

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}

$id_admin  = $_SESSION["admin_id"];
$nom_admin = $_SESSION["admin_nom"] ?? "Admin";

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
catch(PDOException $e) { /* silencieux */ }


//===============================================
// Récupération de la commande demandée
//===============================================

if(!isset($_GET["id_commande"]))
{
    header("Location: liste_commande.php");
    exit();
}

$id_commande = $_GET["id_commande"];

//===============================================
// Infos de la commande + client
//===============================================

$requete = $connexion->prepare("

SELECT commande.*, client.nom AS nom_client, client.prenom AS prenom_client,

client.telephone AS telephone_client, client.email AS email_client

FROM commande

INNER JOIN client ON commande.id_client = client.id_client

WHERE commande.id_commande = ?

");

$requete->execute([$id_commande]);
$commande = $requete->fetch();

if(!$commande)
{
    header("Location: liste_commande.php");
    exit();
}


//===============================================
// Lignes de la commande
//===============================================

$requete_lignes = $connexion->prepare("

SELECT ligne_commande.*, vin.nom_vin, vin.photo

FROM ligne_commande

INNER JOIN vin ON ligne_commande.id_vin = vin.id_vin

WHERE ligne_commande.id_commande = ?

");

$requete_lignes->execute([$id_commande]);
$lignes = $requete_lignes->fetchAll();


//===============================================
// Paiement lié
//===============================================

$requete_paiement = $connexion->prepare("SELECT * FROM paiement WHERE id_commande = ?");
$requete_paiement->execute([$id_commande]);
$paiement = $requete_paiement->fetch();


//===============================================
// Livraison liée
//===============================================

$requete_livraison = $connexion->prepare("SELECT * FROM livraison WHERE id_commande = ?");
$requete_livraison->execute([$id_commande]);
$livraison = $requete_livraison->fetch();


//===============================================
// Utilitaires d'affichage (identiques aux autres pages)
//===============================================

function date_francaise($timestamp)
{
    $jours = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
    $mois  = ['January'=>'janvier','February'=>'février','March'=>'mars','April'=>'avril','May'=>'mai','June'=>'juin','July'=>'juillet','August'=>'août','September'=>'septembre','October'=>'octobre','November'=>'novembre','December'=>'décembre'];
    $jour_en = date("l", $timestamp);
    $mois_en = date("F", $timestamp);
    return $jours[$jour_en] . " " . date("d", $timestamp) . " " . $mois[$mois_en] . " " . date("Y", $timestamp);
}

function temps_ecoule($date)
{
    if(!$date) return '';
    $diff = time() - strtotime($date);
    if($diff < 60)    return "à l'instant";
    if($diff < 3600)  return floor($diff / 60) . " min";
    if($diff < 86400) return floor($diff / 3600) . " h";
    return date("d/m H:i", strtotime($date));
}

function normaliser_statut($valeur)
{
    $valeur = (string) $valeur;
    $valeur = trim($valeur);

    if($valeur !== '' && !mb_check_encoding($valeur, 'UTF-8'))
    {
        $converti = @mb_convert_encoding($valeur, 'UTF-8', 'ISO-8859-1');
        if($converti !== false)
        {
            $valeur = $converti;
        }
    }

    return $valeur;
}

function statut_correspond($valeur, $reference)
{
    $a = mb_strtolower(strtr($valeur,    ["é"=>"e","è"=>"e","ê"=>"e","à"=>"a","ù"=>"u"]));
    $b = mb_strtolower(strtr($reference, ["é"=>"e","è"=>"e","ê"=>"e","à"=>"a","ù"=>"u"]));
    return trim($a) === trim($b);
}

$statut_commande_propre = normaliser_statut($commande["statut"]);
$couleur_badge_commande = "secondary";
if(statut_correspond($statut_commande_propre, "Validée")) $couleur_badge_commande = "info";
if(statut_correspond($statut_commande_propre, "En cours")) $couleur_badge_commande = "primary";
if(statut_correspond($statut_commande_propre, "Livrée"))   $couleur_badge_commande = "success";
if(statut_correspond($statut_commande_propre, "Annulée"))  $couleur_badge_commande = "danger";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Détail Commande #<?php echo $commande["id_commande"]; ?></title>
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
   STYLES POUR LE DÉTAIL DE COMMANDE
   (IDENTIQUE À LA LISTE DES PAIEMENTS)
   ============================================ */

.liste-commande-container {
    max-width: 1300px;
    margin: 0 auto;
}

.liste-commande-card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.liste-commande-card .card-body {
    padding: 1.5rem;
    background: white;
}

/* Bandeau bleu */
.bandeau-commande {
    background: #0d6efd;
    color: white;
    padding: 1rem 1.5rem;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.bandeau-commande h3 {
    font-weight: 600;
    font-size: 1.25rem;
    margin: 0;
}

.bandeau-commande .btn-light {
    background: white;
    color: #0d6efd;
    border: none;
    font-weight: 500;
    padding: 0.4rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
}

.bandeau-commande .btn-light:hover {
    background: #f0f0f0;
}

.bandeau-commande.bandeau-sombre {
    background: #212529;
}

.bandeau-commande.bandeau-info {
    background: #0dcaf0;
    color: #000;
}

.bandeau-commande.bandeau-succes {
    background: #198754;
}

/* Tableau */
.liste-commande-card .table {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.liste-commande-card .table thead th {
    background: #212529;
    color: white;
    border-color: #212529;
    font-weight: 600;
    padding: 0.75rem 0.75rem;
    vertical-align: middle;
}

.liste-commande-card .table td {
    padding: 0.75rem 0.75rem;
    vertical-align: middle;
    border-color: #dee2e6;
}

.liste-commande-card .table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0,0,0,0.02);
}

.liste-commande-card .table-hover tbody tr:hover {
    background-color: rgba(13,110,253,0.05);
}

.bloc-info-titre{
    font-size:.78rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.03em;
    color:var(--texte-att);
    margin-bottom:.6rem;
}

.bloc-info p{ font-size:.92rem; line-height:1.9; margin-bottom:0; }
.bloc-info strong{ color:var(--texte); }

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
        <a href="liste_commande.php" class="lien-nav actif"><i class="bi bi-bag-check"></i> Commandes</a>
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
        <a href="liste_commande.php" class="bouton-retour"><i class="bi bi-arrow-left"></i> Retour</a>
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

        <div class="liste-commande-container">

            <!-- ============================================
                 BLOC CLIENT + COMMANDE
                 ============================================ -->
            <div class="card liste-commande-card">
                <div class="bandeau-commande">
                    <h3><i class="bi bi-bag-check me-2"></i>Commande #<?php echo $commande["id_commande"]; ?></h3>
                    <a href="modifier_commande.php?id_commande=<?php echo $commande["id_commande"]; ?>" class="btn btn-light btn-sm"><i class="bi bi-pencil-square me-1"></i>Modifier</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 bloc-info">
                            <div class="bloc-info-titre">Informations client</div>
                            <p>
                                <strong>Nom :</strong> <?php echo htmlspecialchars($commande["nom_client"] . " " . $commande["prenom_client"]); ?><br>
                                <strong>Téléphone :</strong> <?php echo htmlspecialchars($commande["telephone_client"]); ?><br>
                                <strong>Email :</strong> <?php echo htmlspecialchars($commande["email_client"]); ?>
                            </p>
                        </div>
                        <div class="col-md-6 bloc-info">
                            <div class="bloc-info-titre">Informations commande</div>
                            <p>
                                <strong>Date :</strong> <?php echo htmlspecialchars($commande["date_commande"]); ?><br>
                                <strong>Mode livraison :</strong> <?php echo htmlspecialchars($commande["mode_livraison"]); ?><br>
                                <strong>Statut :</strong> <span class="badge rounded-pill text-bg-<?php echo $couleur_badge_commande; ?>"><?php echo htmlspecialchars($statut_commande_propre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 ARTICLES COMMANDÉS
                 ============================================ -->
            <div class="card liste-commande-card">
                <div class="bandeau-commande bandeau-sombre">
                    <h3><i class="bi bi-cup-straw me-2"></i>Articles commandés</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Vin</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Sous-total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lignes as $ligne): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ligne["nom_vin"]); ?></td>
                                <td><?php echo $ligne["quantite"]; ?></td>
                                <td><?php echo number_format($ligne["prix_unitaire"], 0, ',', ' '); ?> FCFA</td>
                                <td><?php echo number_format($ligne["sous_total"], 0, ',', ' '); ?> FCFA</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Montant total</th>
                                <th><?php echo number_format($commande["montant_total"], 0, ',', ' '); ?> FCFA</th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- ============================================
                     PAIEMENT
                     ============================================ -->
                <div class="col-md-6">
                    <div class="card liste-commande-card">
                        <div class="bandeau-commande bandeau-info">
                            <h3><i class="bi bi-credit-card me-2"></i>Paiement</h3>
                        </div>
                        <div class="card-body bloc-info">
                            <?php if($paiement): ?>
                            <p>
                                <strong>Mode :</strong> <?php echo htmlspecialchars($paiement["mode_paiement"]); ?><br>
                                <strong>Montant :</strong> <?php echo number_format($paiement["montant"], 0, ',', ' '); ?> FCFA<br>
                                <strong>Statut :</strong> <?php echo htmlspecialchars($paiement["statut"]); ?><br>
                                <strong>Référence :</strong> <?php echo htmlspecialchars($paiement["reference_transaction"]); ?>
                            </p>
                            <?php else: ?>
                            <p class="text-muted mb-0">Aucun paiement enregistré pour cette commande.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ============================================
                     LIVRAISON
                     ============================================ -->
                <div class="col-md-6">
                    <div class="card liste-commande-card">
                        <div class="bandeau-commande bandeau-succes">
                            <h3><i class="bi bi-truck me-2"></i>Livraison</h3>
                        </div>
                        <div class="card-body bloc-info">
                            <?php if($livraison): ?>
                            <p>
                                <strong>Adresse :</strong> <?php echo htmlspecialchars($livraison["adresse_livraison"]); ?><br>
                                <strong>Statut :</strong> <?php echo htmlspecialchars($livraison["statut"]); ?><br>
                                <strong>Frais :</strong> <?php echo number_format($livraison["frais_livraison"], 0, ',', ' '); ?> FCFA<br>
                                <strong>N° suivi :</strong> <?php echo htmlspecialchars($livraison["num_suivi"] ?? 'Non renseigné'); ?>
                            </p>
                            <?php else: ?>
                            <p class="text-muted mb-0">Aucune livraison enregistrée pour cette commande.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

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