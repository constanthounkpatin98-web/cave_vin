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


//===============================================
// Petite fonction utilitaire : calcul de croissance en %
//===============================================

function calculer_croissance($actuel, $precedent)
{
    if($precedent == 0)
    {
        return $actuel > 0 ? 100 : 0;
    }

    return round((($actuel - $precedent) / $precedent) * 100, 1);
}


//===============================================
// Statistiques principales (cartes du haut)
//===============================================

$nombre_commandes = $connexion->query("SELECT COUNT(*) AS total FROM commande")->fetch()["total"];

$chiffre_affaires = $connexion->query("
    SELECT COALESCE(SUM(montant),0) AS total FROM paiement WHERE statut = 'Validé'
")->fetch()["total"];

$nombre_clients = $connexion->query("SELECT COUNT(*) AS total FROM client")->fetch()["total"];

$nombre_vins = $connexion->query("SELECT COUNT(*) AS total FROM vin")->fetch()["total"];

$produits_en_stock = $connexion->query("SELECT COALESCE(SUM(quantite_stock),0) AS total FROM vin")->fetch()["total"];

$seuil_stock_faible = 10;

$stock_faible_nb = $connexion->prepare("
    SELECT COUNT(*) AS total FROM vin WHERE quantite_stock <= ?
");
$stock_faible_nb->execute([$seuil_stock_faible]);
$stock_faible_nb = $stock_faible_nb->fetch()["total"];

$commandes_en_cours = $connexion->query("
    SELECT COUNT(*) AS total FROM commande WHERE statut IN ('En attente','Validée','En cours')
")->fetch()["total"];

$paiements_valides = $connexion->query("
    SELECT COUNT(*) AS total FROM paiement WHERE statut = 'Validé'
")->fetch()["total"];


//===============================================
// Croissance mensuelle (commandes / CA / clients)
//===============================================

$debut_mois          = date("Y-m-01");
$debut_mois_dernier  = date("Y-m-01", strtotime("-1 month"));
$fin_mois_dernier     = date("Y-m-t", strtotime("-1 month"));

$commandes_ce_mois = $connexion->prepare("SELECT COUNT(*) AS total FROM commande WHERE date_commande >= ?");
$commandes_ce_mois->execute([$debut_mois]);
$commandes_ce_mois = $commandes_ce_mois->fetch()["total"];

$commandes_mois_dernier = $connexion->prepare("SELECT COUNT(*) AS total FROM commande WHERE date_commande BETWEEN ? AND ?");
$commandes_mois_dernier->execute([$debut_mois_dernier, $fin_mois_dernier . " 23:59:59"]);
$commandes_mois_dernier = $commandes_mois_dernier->fetch()["total"];

$ca_ce_mois = $connexion->prepare("SELECT COALESCE(SUM(montant),0) AS total FROM paiement WHERE statut='Validé' AND date_paiement >= ?");
$ca_ce_mois->execute([$debut_mois]);
$ca_ce_mois = $ca_ce_mois->fetch()["total"];

$ca_mois_dernier = $connexion->prepare("SELECT COALESCE(SUM(montant),0) AS total FROM paiement WHERE statut='Validé' AND date_paiement BETWEEN ? AND ?");
$ca_mois_dernier->execute([$debut_mois_dernier, $fin_mois_dernier . " 23:59:59"]);
$ca_mois_dernier = $ca_mois_dernier->fetch()["total"];

$clients_ce_mois = $connexion->prepare("SELECT COUNT(*) AS total FROM client WHERE date_inscription >= ?");
$clients_ce_mois->execute([$debut_mois]);
$clients_ce_mois = $clients_ce_mois->fetch()["total"];

$clients_mois_dernier = $connexion->prepare("SELECT COUNT(*) AS total FROM client WHERE date_inscription BETWEEN ? AND ?");
$clients_mois_dernier->execute([$debut_mois_dernier, $fin_mois_dernier . " 23:59:59"]);
$clients_mois_dernier = $clients_mois_dernier->fetch()["total"];

$croissance_commandes = calculer_croissance($commandes_ce_mois, $commandes_mois_dernier);
$croissance_ca        = calculer_croissance($ca_ce_mois, $ca_mois_dernier);
$croissance_clients    = calculer_croissance($clients_ce_mois, $clients_mois_dernier);


//===============================================
// Commandes récentes
//===============================================

$requete_commandes_recentes = $connexion->query("
    SELECT commande.id_commande, commande.montant_total, commande.statut, commande.date_commande,
           client.nom, client.prenom
    FROM commande
    INNER JOIN client ON commande.id_client = client.id_client
    ORDER BY commande.date_commande DESC
    LIMIT 5
");


//===============================================
// Évolution des ventes (12 derniers jours)
//===============================================

$requete_evolution = $connexion->query("
    SELECT DATE(date_commande) AS jour, COUNT(*) AS nb_commandes, COALESCE(SUM(montant_total),0) AS montant
    FROM commande
    WHERE date_commande >= DATE_SUB(CURDATE(), INTERVAL 11 DAY)
    GROUP BY DATE(date_commande)
    ORDER BY jour ASC
");

$evolution_par_jour = [];
while($ligne = $requete_evolution->fetch())
{
    $evolution_par_jour[$ligne["jour"]] = $ligne;
}

$labels_evolution   = [];
$commandes_evolution = [];
$montants_evolution   = [];

for($i = 11; $i >= 0; $i--)
{
    $jour = date("Y-m-d", strtotime("-$i day"));
    $labels_evolution[]    = date("d/m", strtotime($jour));
    $commandes_evolution[] = isset($evolution_par_jour[$jour]) ? (int)$evolution_par_jour[$jour]["nb_commandes"] : 0;
    $montants_evolution[]  = isset($evolution_par_jour[$jour]) ? (float)$evolution_par_jour[$jour]["montant"] : 0;
}


//===============================================
// Répartition des vins par couleur
//===============================================

$requete_couleurs = $connexion->query("
    SELECT couleur, COUNT(*) AS total FROM vin GROUP BY couleur
");

$labels_couleur = [];
$valeurs_couleur = [];
$couleurs_hex = [
    'Rouge'        => '#dc3545',
    'Blanc'        => '#ffc107',
    'Rosé'         => '#e879a6',
    'Effervescent' => '#6f42c1',
];

while($ligne = $requete_couleurs->fetch())
{
    $labels_couleur[]  = $ligne["couleur"];
    $valeurs_couleur[] = (int)$ligne["total"];
}


//===============================================
// Répartition des paiements par statut
//===============================================

$requete_paiements = $connexion->query("
    SELECT statut, COALESCE(SUM(montant),0) AS total, COUNT(*) AS nb
    FROM paiement
    GROUP BY statut
");

$paiements_par_statut = [];
$total_encaisse = 0;

while($ligne = $requete_paiements->fetch())
{
    $paiements_par_statut[$ligne["statut"]] = $ligne;
    $total_encaisse += $ligne["total"];
}


//===============================================
// Répartition des livraisons par statut
//===============================================

$requete_livraisons = $connexion->query("
    SELECT statut, COUNT(*) AS total FROM livraison GROUP BY statut
");

$livraisons_par_statut = [];
$total_livraisons = 0;

while($ligne = $requete_livraisons->fetch())
{
    $livraisons_par_statut[$ligne["statut"]] = $ligne["total"];
    $total_livraisons += $ligne["total"];
}


//===============================================
// Avis et notifications
//===============================================

$avis_total = $connexion->query("SELECT COUNT(*) AS total FROM avis")->fetch()["total"];
$avis_en_attente = $connexion->query("SELECT COUNT(*) AS total FROM avis WHERE statut = 'En attente'")->fetch()["total"];

$notifications_total = $connexion->query("SELECT COUNT(*) AS total FROM notification")->fetch()["total"];
$notifications_non_lues = $connexion->query("SELECT COUNT(*) AS total FROM notification WHERE statut = 'Non lue'")->fetch()["total"];


//===============================================
// Vins en stock faible (liste)
//===============================================

$requete_stock_faible = $connexion->prepare("
    SELECT nom_vin, millesime, quantite_stock
    FROM vin
    WHERE quantite_stock <= ?
    ORDER BY quantite_stock ASC
    LIMIT 5
");
$requete_stock_faible->execute([$seuil_stock_faible]);


//===============================================
// Top 5 des vins les plus vendus
//===============================================

$requete_top_vins = $connexion->query("
    SELECT vin.nom_vin, SUM(ligne_commande.quantite) AS total_vendu
    FROM ligne_commande
    INNER JOIN vin ON ligne_commande.id_vin = vin.id_vin
    GROUP BY vin.id_vin
    ORDER BY total_vendu DESC
    LIMIT 5
");

$top_vins = [];
$top_vins_max = 1;

while($ligne = $requete_top_vins->fetch())
{
    $top_vins[] = $ligne;
    $top_vins_max = max($top_vins_max, (int)$ligne["total_vendu"]);
}


//===============================================
// Activité récente (fusion de plusieurs tables)
//===============================================

$requete_activite = $connexion->query("
    (SELECT 'commande' AS type_evt,
            CONCAT('Nouvelle commande #', commande.id_commande, ' par ', client.prenom, ' ', client.nom) AS texte,
            commande.montant_total AS montant,
            commande.date_commande AS date_evt
     FROM commande
     INNER JOIN client ON commande.id_client = client.id_client)

    UNION ALL

    (SELECT 'paiement' AS type_evt,
            CONCAT('Paiement reçu de ', client.prenom, ' ', client.nom) AS texte,
            paiement.montant AS montant,
            paiement.date_paiement AS date_evt
     FROM paiement
     INNER JOIN commande ON paiement.id_commande = commande.id_commande
     INNER JOIN client ON commande.id_client = client.id_client
     WHERE paiement.statut = 'Validé')

    UNION ALL

    (SELECT 'livraison' AS type_evt,
            CONCAT('Livraison effectuée pour la commande #', livraison.id_commande) AS texte,
            NULL AS montant,
            livraison.date_livraison AS date_evt
     FROM livraison
     WHERE livraison.statut = 'Livrée' AND livraison.date_livraison IS NOT NULL)

    UNION ALL

    (SELECT 'client' AS type_evt,
            CONCAT('Nouveau client inscrit : ', client.prenom, ' ', client.nom) AS texte,
            NULL AS montant,
            client.date_inscription AS date_evt
     FROM client)

    ORDER BY date_evt DESC
    LIMIT 6
");


//===============================================
// Enregistrement / mise à jour du snapshot dans tableau_bord
//===============================================

$requete_existe = $connexion->prepare("SELECT id_tableau_bord FROM tableau_bord WHERE id_admin = ?");
$requete_existe->execute([$id_admin]);
$snapshot = $requete_existe->fetch();

if($snapshot)
{
    $requete_maj = $connexion->prepare("
        UPDATE tableau_bord
        SET nombre_commandes = ?, nombre_clients = ?, produits_en_stock = ?,
            paiements_valides = ?, commandes_en_cours = ?, date_mise_a_jour = NOW()
        WHERE id_tableau_bord = ?
    ");

    $requete_maj->execute([
        $nombre_commandes,
        $nombre_clients,
        $produits_en_stock,
        $paiements_valides,
        $commandes_en_cours,
        $snapshot["id_tableau_bord"],
    ]);
}
else
{
    $requete_ins = $connexion->prepare("
        INSERT INTO tableau_bord
        (nom_tableau, nombre_commandes, nombre_clients, produits_en_stock, paiements_valides, commandes_en_cours, id_admin)
        VALUES ('Tableau de bord principal', ?, ?, ?, ?, ?, ?)
    ");

    $requete_ins->execute([
        $nombre_commandes,
        $nombre_clients,
        $produits_en_stock,
        $paiements_valides,
        $commandes_en_cours,
        $id_admin,
    ]);
}


//===============================================
// Petits utilitaires d'affichage
//===============================================

function badge_statut_commande($statut)
{
    $classes = [
        'Livrée'     => 'text-bg-success',
        'En cours'   => 'text-bg-info',
        'Validée'    => 'text-bg-primary',
        'En attente' => 'text-bg-warning',
        'Annulée'    => 'text-bg-danger',
    ];

    $classe = $classes[$statut] ?? 'text-bg-secondary';

    return '<span class="badge rounded-pill ' . $classe . '">' . htmlspecialchars($statut) . '</span>';
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

function icone_activite($type)
{
    $icones = [
        'commande'  => ['bi-cart-check', 'texte-succes'],
        'paiement'  => ['bi-currency-exchange', 'texte-info'],
        'livraison' => ['bi-truck', 'texte-primaire'],
        'client'    => ['bi-person-plus', 'texte-violet'],
    ];

    return $icones[$type] ?? ['bi-circle', 'texte-info'];
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
<title>Tableau de bord — Gestion des Vins</title>
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

/* ---------- Sidebar ---------- */

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

.logo-app .titre-logo{
    font-weight:700;
    font-size:.95rem;
    color:#fff;
    line-height:1.1;
}

.logo-app .sous-titre-logo{
    font-size:.72rem;
    color:#8891b3;
}

.nav-laterale{
    flex:1;
    overflow-y:auto;
    padding:.9rem .7rem;
}

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

.nav-laterale .lien-nav:hover{
    background:var(--navy-hover);
    color:#fff;
}

.nav-laterale .lien-nav.actif{
    background:var(--bleu);
    color:#fff;
}

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

/* ---------- Contenu principal ---------- */

.contenu-principal{
    margin-left:250px;
    min-height:100vh;
}

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

.barre-superieure .date-jour{
    font-size:.85rem;
    color:var(--texte-att);
    display:flex;
    align-items:center;
    gap:.4rem;
}

.barre-superieure .zone-droite{
    display:flex;
    align-items:center;
    gap:1.2rem;
}

.cloche-notif{
    position:relative;
    font-size:1.15rem;
    color:var(--texte-att);
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

.zone-corps{ padding:1.7rem 1.8rem 2.5rem; }

.entete-page h2{ font-weight:800; margin-bottom:.15rem; }
.entete-page p{ color:var(--texte-att); margin-bottom:0; font-size:.9rem; }

/* ---------- Cartes stats ---------- */

.carte{
    background:var(--carte);
    border-radius:var(--rayon);
    border:1px solid var(--bordure);
    box-shadow:0 1px 2px rgba(16,24,40,.04);
}

.carte-stat{
    padding:1.15rem 1.25rem;
    display:flex;
    gap:.9rem;
}

.icone-stat{
    width:46px; height:46px;
    border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.15rem;
    color:#fff;
    flex-shrink:0;
}

.carte-stat .libelle-stat{
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--texte-att);
    font-weight:600;
}

.carte-stat .valeur-stat{
    font-size:1.5rem;
    font-weight:800;
    line-height:1.15;
    margin-top:.1rem;
}

.carte-stat .evolution-stat{
    font-size:.76rem;
    font-weight:600;
    margin-top:.25rem;
}

.evolution-positive{ color:var(--vert); }
.evolution-negative{ color:var(--rouge); }
.lien-stat{ font-size:.76rem; font-weight:600; color:var(--bleu); text-decoration:none; }

/* ---------- En-têtes de cartes ---------- */

.entete-carte{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:1rem 1.25rem;
    border-bottom:1px solid var(--bordure);
}

.entete-carte h6{ font-weight:700; margin:0; font-size:.92rem; }
.entete-carte a{ font-size:.78rem; font-weight:600; color:var(--bleu); text-decoration:none; }

.corps-carte{ padding:1.1rem 1.25rem; }

/* ---------- Tableau commandes récentes ---------- */

.table-vins{ font-size:.83rem; margin:0; }
.table-vins thead th{
    color:var(--texte-att);
    font-weight:600;
    text-transform:uppercase;
    font-size:.68rem;
    letter-spacing:.03em;
    border-bottom:1px solid var(--bordure);
    padding-bottom:.6rem;
}
.table-vins td{ padding:.6rem .3rem; vertical-align:middle; border-bottom:1px solid var(--bordure); }
.table-vins tr:last-child td{ border-bottom:none; }

/* ---------- Légende répartition ---------- */

.ligne-legende{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:.4rem 0;
    font-size:.82rem;
}
.puce-legende{ width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:.5rem; }
.ligne-legende .valeur-legende{ font-weight:700; color:var(--texte); }

/* ---------- Stock faible ---------- */

.item-stock-faible{
    display:flex;
    align-items:center;
    gap:.75rem;
    padding:.55rem 0;
    border-bottom:1px solid var(--bordure);
}
.item-stock-faible:last-child{ border-bottom:none; }

.icone-bouteille{
    width:38px; height:38px;
    border-radius:10px;
    background:var(--bleu-clair);
    color:var(--bleu);
    display:flex; align-items:center; justify-content:center;
    font-size:1rem;
    flex-shrink:0;
}

.item-stock-faible .nom-vin-sf{ font-size:.84rem; font-weight:600; }
.item-stock-faible .qte-vin-sf{ font-size:.76rem; color:var(--texte-att); }

/* ---------- Résumé paiements / livraisons / avis ---------- */

.ligne-resume{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:.55rem 0;
    font-size:.85rem;
}
.ligne-resume .libelle-resume{ display:flex; align-items:center; gap:.55rem; }
.puce-ronde{ width:9px; height:9px; border-radius:50%; display:inline-block; }

/* ---------- Activité récente ---------- */

.item-activite{
    display:flex;
    align-items:flex-start;
    gap:.8rem;
    padding:.65rem 0;
}
.icone-activite{
    width:34px; height:34px;
    border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    font-size:.9rem;
    flex-shrink:0;
    background:var(--bleu-clair);
    color:var(--bleu);
}
.item-activite .texte-activite{ font-size:.83rem; }
.item-activite .heure-activite{ font-size:.73rem; color:var(--texte-att); }
.item-activite .montant-activite{ font-size:.83rem; font-weight:700; margin-left:auto; white-space:nowrap; }

/* ---------- Top vins ---------- */

.item-top-vin{ margin-bottom:.85rem; }
.item-top-vin .en-tete-top{
    display:flex; justify-content:space-between; font-size:.83rem; margin-bottom:.3rem;
}
.item-top-vin .en-tete-top .rang-vin{ font-weight:700; color:var(--texte-att); margin-right:.4rem; }
.barre-progression-fine{ height:7px; border-radius:99px; background:var(--bordure); overflow:hidden; }
.barre-progression-fine span{ display:block; height:100%; border-radius:99px; background:var(--bleu); }

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
        <a href="tableau_bord.php" class="lien-nav actif"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
        <a href="liste_client.php" class="lien-nav"><i class="bi bi-people"></i> Clients</a>
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
        <a href="parametres.php" class="lien-nav"><i class="bi bi-gear"></i> Paramètres</a>
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
        <div class="date-jour"><i class="bi bi-calendar3"></i> <?php echo date_francaise(time()); ?></div>
        <div class="zone-droite">
            <div class="cloche-notif">
                <i class="bi bi-bell"></i>
                <?php if($notifications_non_lues > 0): ?>
                    <span class="point-badge"><?php echo min($notifications_non_lues, 99); ?></span>
                <?php endif; ?>
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
            <i class="bi bi-grid-1x2-fill fs-4 text-primary"></i>
            <div>
                <h2>Tableau de bord</h2>
                <p>Vue d'ensemble de votre activité</p>
            </div>
        </div>

        <!-- ===== Cartes statistiques ===== -->
        <div class="row g-3 mb-3">

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--bleu);"><i class="bi bi-cart-check"></i></div>
                    <div>
                        <div class="libelle-stat">Commandes</div>
                        <div class="valeur-stat"><?php echo number_format($nombre_commandes, 0, ',', ' '); ?></div>
                        <div class="evolution-stat <?php echo $croissance_commandes >= 0 ? 'evolution-positive' : 'evolution-negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $croissance_commandes >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($croissance_commandes); ?>% ce mois
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--vert);"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="libelle-stat">Chiffre d'affaires</div>
                        <div class="valeur-stat"><?php echo number_format($chiffre_affaires, 0, ',', ' '); ?> F</div>
                        <div class="evolution-stat <?php echo $croissance_ca >= 0 ? 'evolution-positive' : 'evolution-negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $croissance_ca >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($croissance_ca); ?>% ce mois
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--violet);"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="libelle-stat">Clients</div>
                        <div class="valeur-stat"><?php echo number_format($nombre_clients, 0, ',', ' '); ?></div>
                        <div class="evolution-stat <?php echo $croissance_clients >= 0 ? 'evolution-positive' : 'evolution-negative'; ?>">
                            <i class="bi bi-arrow-<?php echo $croissance_clients >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($croissance_clients); ?>% ce mois
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--jaune);"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="libelle-stat">Produits (vins)</div>
                        <div class="valeur-stat"><?php echo number_format($nombre_vins, 0, ',', ' '); ?></div>
                        <a href="../vin/liste_vin.php" class="lien-stat">Voir le stock</a>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--rouge);"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <div class="libelle-stat">Stock faible</div>
                        <div class="valeur-stat"><?php echo number_format($stock_faible_nb, 0, ',', ' '); ?></div>
                        <a href="../vin/liste_vin.php?filtre=stock_faible" class="lien-stat">Voir les détails</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Commandes récentes + Évolution + Répartition ===== -->
        <div class="row g-3 mb-3">

            <div class="col-lg-5">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Commandes récentes</h6>
                        <a href="../commande/liste_commande.php">Voir toutes</a>
                    </div>
                    <div class="corps-carte">
                        <div class="table-responsive">
                        <table class="table-vins">
                            <thead>
                                <tr>
                                    <th>N° Commande</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $aucune_commande = true; ?>
                                <?php while($cmd = $requete_commandes_recentes->fetch()): $aucune_commande = false; ?>
                                <tr>
                                    <td class="fw-semibold">CMD-<?php echo str_pad($cmd["id_commande"], 4, "0", STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($cmd["prenom"] . " " . $cmd["nom"]); ?></td>
                                    <td><?php echo number_format($cmd["montant_total"], 0, ',', ' '); ?> F</td>
                                    <td><?php echo badge_statut_commande($cmd["statut"]); ?></td>
                                    <td class="text-muted"><?php echo date("d/m/Y", strtotime($cmd["date_commande"])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($aucune_commande): ?>
                                <tr><td colspan="5" class="text-muted text-center py-3">Aucune commande enregistrée.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Évolution des ventes</h6>
                        <span class="text-muted small">12 derniers jours</span>
                    </div>
                    <div class="corps-carte">
                        <canvas id="graphiqueEvolution" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Répartition par couleur</h6>
                    </div>
                    <div class="corps-carte">
                        <canvas id="graphiqueCouleurs" height="170"></canvas>
                        <div class="mt-3">
                            <?php
                            $total_vins_couleur = array_sum($valeurs_couleur);
                            foreach($labels_couleur as $i => $label):
                                $hex = $couleurs_hex[$label] ?? '#adb5bd';
                                $pct = $total_vins_couleur > 0 ? round(($valeurs_couleur[$i] / $total_vins_couleur) * 100) : 0;
                            ?>
                            <div class="ligne-legende">
                                <span><span class="puce-legende" style="background:<?php echo $hex; ?>;"></span><?php echo htmlspecialchars($label); ?></span>
                                <span class="valeur-legende"><?php echo $pct; ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== Paiements / Livraisons / Avis ===== -->
        <div class="row g-3 mb-3">

            <div class="col-lg-4">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Paiements</h6>
                        <a href="../paiement/liste_paiement.php">Voir tout</a>
                    </div>
                    <div class="corps-carte d-flex align-items-center gap-3">
                        <canvas id="graphiquePaiements" width="120" height="120" style="max-width:120px;"></canvas>
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5"><?php echo number_format($total_encaisse, 0, ',', ' '); ?> F</div>
                            <div class="text-muted small mb-2">Total encaissé</div>
                            <?php
                            $couleurs_paiement = ['Validé' => 'var(--vert)', 'En attente' => 'var(--jaune)', 'Échoué' => 'var(--rouge)', 'Remboursé' => 'var(--texte-att)'];
                            foreach($paiements_par_statut as $statut => $info):
                                $couleur = $couleurs_paiement[$statut] ?? 'var(--texte-att)';
                            ?>
                            <div class="ligne-resume">
                                <span class="libelle-resume"><span class="puce-ronde" style="background:<?php echo $couleur; ?>;"></span><?php echo htmlspecialchars($statut); ?></span>
                                <span class="fw-semibold"><?php echo number_format($info["total"], 0, ',', ' '); ?> F (<?php echo $info["nb"]; ?>)</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Livraisons</h6>
                        <a href="../livraison/liste_livraison.php">Voir tout</a>
                    </div>
                    <div class="corps-carte">
                        <?php
                        $icones_livraison = ['Livrée' => ['bi-truck', 'var(--vert)'], 'Expédiée' => ['bi-send', 'var(--bleu)'], 'En préparation' => ['bi-clock-history', 'var(--jaune)'], 'Retour' => ['bi-arrow-return-left', 'var(--rouge)']];
                        foreach($livraisons_par_statut as $statut => $total):
                            $pct = $total_livraisons > 0 ? round(($total / $total_livraisons) * 100) : 0;
                            [$icone, $couleur] = $icones_livraison[$statut] ?? ['bi-box', 'var(--texte-att)'];
                        ?>
                        <div class="ligne-resume">
                            <span class="libelle-resume"><i class="bi <?php echo $icone; ?>" style="color:<?php echo $couleur; ?>;"></i> <?php echo htmlspecialchars($statut); ?></span>
                            <span class="fw-semibold"><?php echo $total; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($livraisons_par_statut)): ?>
                        <p class="text-muted small mb-0">Aucune livraison enregistrée.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Avis &amp; Notifications</h6>
                        <a href="../avis/liste_avis.php">Voir tout</a>
                    </div>
                    <div class="corps-carte">
                        <div class="ligne-resume">
                            <span class="libelle-resume"><i class="bi bi-star-fill" style="color:var(--jaune);"></i> Avis clients</span>
                            <span class="fw-semibold"><?php echo $avis_total; ?> <span class="text-muted fw-normal">(<?php echo $avis_en_attente; ?> en attente)</span></span>
                        </div>
                        <div class="ligne-resume">
                            <span class="libelle-resume"><i class="bi bi-bell-fill" style="color:var(--bleu);"></i> Notifications</span>
                            <span class="fw-semibold"><?php echo $notifications_total; ?> <span class="text-muted fw-normal">(<?php echo $notifications_non_lues; ?> non lues)</span></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== Activité récente + Top 5 vins ===== -->
        <div class="row g-3">

            <div class="col-lg-6">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Activité récente</h6>
                    </div>
                    <div class="corps-carte">
                        <?php $aucune_activite = true; ?>
                        <?php while($evt = $requete_activite->fetch()): $aucune_activite = false; [$icone, $classe] = icone_activite($evt["type_evt"]); ?>
                        <div class="item-activite">
                            <div class="icone-activite"><i class="bi <?php echo $icone; ?>"></i></div>
                            <div>
                                <div class="texte-activite"><?php echo htmlspecialchars($evt["texte"]); ?></div>
                                <div class="heure-activite"><?php echo temps_ecoule($evt["date_evt"]); ?></div>
                            </div>
                            <?php if($evt["montant"] !== null): ?>
                            <div class="montant-activite"><?php echo number_format($evt["montant"], 0, ',', ' '); ?> F</div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                        <?php if($aucune_activite): ?>
                        <p class="text-muted small mb-0">Aucune activité récente.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Vins en stock faible</h6>
                        <a href="../vin/liste_vin.php">Voir tout</a>
                    </div>
                    <div class="corps-carte">
                        <?php $aucun_stock_faible = true; ?>
                        <?php while($v = $requete_stock_faible->fetch()): $aucun_stock_faible = false; ?>
                        <div class="item-stock-faible">
                            <div class="icone-bouteille"><i class="bi bi-cup-straw"></i></div>
                            <div>
                                <div class="nom-vin-sf"><?php echo htmlspecialchars($v["nom_vin"]); ?><?php echo $v["millesime"] ? " " . $v["millesime"] : ""; ?></div>
                                <div class="qte-vin-sf">Stock : <?php echo $v["quantite_stock"]; ?> bouteille<?php echo $v["quantite_stock"] > 1 ? "s" : ""; ?></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php if($aucun_stock_faible): ?>
                        <p class="text-muted small mb-0">Aucun vin en stock faible.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Top 5 des vins</h6>
                        <a href="rapports.php">Voir rapport</a>
                    </div>
                    <div class="corps-carte">
                        <?php if(empty($top_vins)): ?>
                        <p class="text-muted small mb-0">Aucune vente enregistrée.</p>
                        <?php endif; ?>
                        <?php foreach($top_vins as $i => $v):
                            $pct = round(($v["total_vendu"] / $top_vins_max) * 100);
                        ?>
                        <div class="item-top-vin">
                            <div class="en-tete-top">
                                <span><span class="rang-vin"><?php echo $i + 1; ?></span><?php echo htmlspecialchars($v["nom_vin"]); ?></span>
                                <span class="fw-semibold"><?php echo $v["total_vendu"]; ?></span>
                            </div>
                            <div class="barre-progression-fine"><span style="width:<?php echo $pct; ?>%;"></span></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>

// ---- Évolution des ventes (ligne) ----
new Chart(document.getElementById('graphiqueEvolution'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels_evolution); ?>,
        datasets: [
            {
                label: 'Commandes',
                data: <?php echo json_encode($commandes_evolution); ?>,
                borderColor: '#2f6fed',
                backgroundColor: 'rgba(47,111,237,.08)',
                tension: .35,
                fill: true,
                pointRadius: 3,
                yAxisID: 'y'
            },
            {
                label: 'Montant (F)',
                data: <?php echo json_encode($montants_evolution); ?>,
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124,58,237,.06)',
                tension: .35,
                fill: true,
                pointRadius: 3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
        scales: {
            y:  { beginAtZero: true, position: 'left', grid: { drawOnChartArea: false } },
            y1: { beginAtZero: true, position: 'right', grid: { display: false } }
        }
    }
});

// ---- Répartition par couleur (donut) ----
new Chart(document.getElementById('graphiqueCouleurs'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($labels_couleur); ?>,
        datasets: [{
            data: <?php echo json_encode($valeurs_couleur); ?>,
            backgroundColor: <?php echo json_encode(array_map(fn($l) => $couleurs_hex[$l] ?? '#adb5bd', $labels_couleur)); ?>,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        cutout: '68%',
        plugins: { legend: { display: false } }
    }
});

// ---- Paiements (donut) ----
new Chart(document.getElementById('graphiquePaiements'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($paiements_par_statut)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($paiements_par_statut, 'total')); ?>,
            backgroundColor: ['#16a34a', '#f59e0b', '#ef4444', '#9ca3af'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        cutout: '70%',
        plugins: { legend: { display: false } }
    }
});

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
<script src="../assets/js/sonnerie_admin.js"></script>
</body>
</html>