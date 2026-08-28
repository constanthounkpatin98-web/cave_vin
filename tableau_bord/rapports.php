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
catch(PDOException $e) { /* silencieux ici, géré comme le reste plus bas */ }


//===============================================
// Période sélectionnée (filtre du rapport)
//===============================================

$periodes_valides = ['7' => '7 derniers jours', '30' => '30 derniers jours', 'mois' => 'Mois en cours', 'annee' => 'Année en cours', 'tout' => 'Depuis le début'];
$periode = $_GET['periode'] ?? '30';
if(!array_key_exists($periode, $periodes_valides)) { $periode = '30'; }

switch($periode)
{
    case '7':
        $date_debut_periode = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $grouper_par_mois = false;
        break;
    case 'mois':
        $date_debut_periode = date('Y-m-01 00:00:00');
        $grouper_par_mois = false;
        break;
    case 'annee':
        $date_debut_periode = date('Y-01-01 00:00:00');
        $grouper_par_mois = true;
        break;
    case 'tout':
        $date_debut_periode = '2000-01-01 00:00:00';
        $grouper_par_mois = true;
        break;
    case '30':
    default:
        $date_debut_periode = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $grouper_par_mois = false;
        break;
}


//===============================================
// Export CSV (si demandé) — s'exécute avant tout affichage HTML
//===============================================

if(isset($_GET['export']) && in_array($_GET['export'], ['top_vins', 'top_clients']))
{
    try
    {
        if($_GET['export'] === 'top_vins')
        {
            $req = $connexion->prepare("
                SELECT vin.nom_vin, vin.couleur, SUM(ligne_commande.quantite) AS quantite_vendue,
                       SUM(ligne_commande.sous_total) AS chiffre_affaires
                FROM ligne_commande
                INNER JOIN vin ON ligne_commande.id_vin = vin.id_vin
                INNER JOIN commande ON ligne_commande.id_commande = commande.id_commande
                WHERE commande.date_commande >= ?
                GROUP BY vin.id_vin
                ORDER BY chiffre_affaires DESC
            ");
            $req->execute([$date_debut_periode]);
            $lignes = $req->fetchAll();
            $nom_fichier = 'top_vins_' . date('Ymd_His') . '.csv';
            $entetes = ['Vin', 'Couleur', 'Quantité vendue', 'Chiffre d\'affaires (F)'];
        }
        else
        {
            $req = $connexion->prepare("
                SELECT client.nom, client.prenom, client.email, COUNT(DISTINCT commande.id_commande) AS nb_commandes,
                       SUM(commande.montant_total) AS total_depense
                FROM commande
                INNER JOIN client ON commande.id_client = client.id_client
                WHERE commande.date_commande >= ?
                GROUP BY client.id_client
                ORDER BY total_depense DESC
            ");
            $req->execute([$date_debut_periode]);
            $lignes = $req->fetchAll();
            $nom_fichier = 'top_clients_' . date('Ymd_His') . '.csv';
            $entetes = ['Nom', 'Prénom', 'Email', 'Nombre de commandes', 'Total dépensé (F)'];
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8 pour un affichage correct des accents dans Excel

        $sortie = fopen('php://output', 'w');
        fputcsv($sortie, $entetes, ';');

        foreach($lignes as $l)
        {
            fputcsv($sortie, array_values($l) === $l ? $l : array_values($l), ';');
        }

        fclose($sortie);
        exit();
    }
    catch(PDOException $e)
    {
        // si l'export échoue, on laisse la page normale s'afficher avec l'erreur
    }
}


//===============================================
// Valeurs par défaut
//===============================================

$erreur_chargement = null;

$ca_periode          = 0;
$commandes_periode   = 0;
$panier_moyen        = 0;
$nouveaux_clients    = 0;

$labels_evolution    = [];
$commandes_evolution = [];
$montants_evolution   = [];

$labels_statut_cmd = [];
$valeurs_statut_cmd = [];
$couleurs_statut_cmd = [];

$labels_categorie = [];
$valeurs_categorie = [];

$top_vins    = [];
$top_clients = [];
$mouvements_stock = [];


//===============================================
// Requêtes (protégées : la page reste utilisable même si une table diffère)
//===============================================

try
{

    //---------- KPIs ----------

    $req = $connexion->prepare("SELECT COALESCE(SUM(montant_total),0) AS total, COUNT(*) AS nb FROM commande WHERE date_commande >= ?");
    $req->execute([$date_debut_periode]);
    $r = $req->fetch();
    $ca_periode        = (float)$r['total'];
    $commandes_periode = (int)$r['nb'];
    $panier_moyen       = $commandes_periode > 0 ? $ca_periode / $commandes_periode : 0;

    $req = $connexion->prepare("SELECT COUNT(*) AS total FROM client WHERE date_inscription >= ?");
    $req->execute([$date_debut_periode]);
    $nouveaux_clients = (int)$req->fetch()['total'];


    //---------- Évolution des commandes / CA sur la période ----------

    if($grouper_par_mois)
    {
        $req = $connexion->prepare("
            SELECT DATE_FORMAT(date_commande, '%Y-%m') AS periode, COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS montant
            FROM commande
            WHERE date_commande >= ?
            GROUP BY periode
            ORDER BY periode ASC
        ");
        $req->execute([$date_debut_periode]);

        while($ligne = $req->fetch())
        {
            $labels_evolution[]    = date('m/Y', strtotime($ligne['periode'] . '-01'));
            $commandes_evolution[] = (int)$ligne['nb'];
            $montants_evolution[]  = (float)$ligne['montant'];
        }
    }
    else
    {
        $req = $connexion->prepare("
            SELECT DATE(date_commande) AS jour, COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS montant
            FROM commande
            WHERE date_commande >= ?
            GROUP BY jour
            ORDER BY jour ASC
        ");
        $req->execute([$date_debut_periode]);

        $par_jour = [];
        while($ligne = $req->fetch())
        {
            $par_jour[$ligne['jour']] = $ligne;
        }

        $nb_jours = (int)ceil((time() - strtotime($date_debut_periode)) / 86400);
        $nb_jours = max(1, min($nb_jours, 90));

        for($i = $nb_jours - 1; $i >= 0; $i--)
        {
            $jour = date('Y-m-d', strtotime("-$i day"));
            $labels_evolution[]    = date('d/m', strtotime($jour));
            $commandes_evolution[] = isset($par_jour[$jour]) ? (int)$par_jour[$jour]['nb'] : 0;
            $montants_evolution[]  = isset($par_jour[$jour]) ? (float)$par_jour[$jour]['montant'] : 0;
        }
    }


    //---------- Répartition des commandes par statut ----------

    $couleurs_statuts = [
        'Livrée'     => '#16a34a',
        'En cours'   => '#06b6d4',
        'Validée'    => '#2f6fed',
        'En attente' => '#f59e0b',
        'Annulée'    => '#ef4444',
    ];

    $req = $connexion->prepare("SELECT statut, COUNT(*) AS total FROM commande WHERE date_commande >= ? GROUP BY statut");
    $req->execute([$date_debut_periode]);

    while($ligne = $req->fetch())
    {
        $labels_statut_cmd[]  = $ligne['statut'];
        $valeurs_statut_cmd[] = (int)$ligne['total'];
        $couleurs_statut_cmd[] = $couleurs_statuts[$ligne['statut']] ?? '#adb5bd';
    }


    //---------- Chiffre d'affaires par catégorie de vin ----------

    $req = $connexion->prepare("
        SELECT categorie.libelle, COALESCE(SUM(ligne_commande.sous_total),0) AS total
        FROM ligne_commande
        INNER JOIN vin ON ligne_commande.id_vin = vin.id_vin
        INNER JOIN commande ON ligne_commande.id_commande = commande.id_commande
        LEFT JOIN categorie ON vin.id_categorie = categorie.id_categorie
        WHERE commande.date_commande >= ?
        GROUP BY categorie.id_categorie
        ORDER BY total DESC
    ");
    $req->execute([$date_debut_periode]);

    while($ligne = $req->fetch())
    {
        $labels_categorie[]  = $ligne['libelle'] ?? 'Sans catégorie';
        $valeurs_categorie[] = (float)$ligne['total'];
    }


    //---------- Top 10 des vins vendus ----------

    $req = $connexion->prepare("
        SELECT vin.nom_vin, vin.couleur, SUM(ligne_commande.quantite) AS quantite_vendue,
               SUM(ligne_commande.sous_total) AS chiffre_affaires
        FROM ligne_commande
        INNER JOIN vin ON ligne_commande.id_vin = vin.id_vin
        INNER JOIN commande ON ligne_commande.id_commande = commande.id_commande
        WHERE commande.date_commande >= ?
        GROUP BY vin.id_vin
        ORDER BY chiffre_affaires DESC
        LIMIT 10
    ");
    $req->execute([$date_debut_periode]);
    $top_vins = $req->fetchAll();


    //---------- Top 5 des clients ----------

    $req = $connexion->prepare("
        SELECT client.id_client, client.nom, client.prenom, client.email,
               COUNT(DISTINCT commande.id_commande) AS nb_commandes,
               SUM(commande.montant_total) AS total_depense
        FROM commande
        INNER JOIN client ON commande.id_client = client.id_client
        WHERE commande.date_commande >= ?
        GROUP BY client.id_client
        ORDER BY total_depense DESC
        LIMIT 5
    ");
    $req->execute([$date_debut_periode]);
    $top_clients = $req->fetchAll();


    //---------- Derniers mouvements de stock ----------

    $req = $connexion->prepare("
        SELECT stock_mouvement.date_mouvement, stock_mouvement.type_mouvement, stock_mouvement.quantite,
               stock_mouvement.stock_apres, vin.nom_vin
        FROM stock_mouvement
        INNER JOIN vin ON stock_mouvement.id_vin = vin.id_vin
        WHERE stock_mouvement.date_mouvement >= ?
        ORDER BY stock_mouvement.date_mouvement DESC
        LIMIT 10
    ");
    $req->execute([$date_debut_periode]);
    $mouvements_stock = $req->fetchAll();

}
catch(PDOException $e)
{
    $erreur_chargement = $e->getMessage();
}


//===============================================
// Utilitaires d'affichage (identiques au tableau de bord)
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
<title>Rapports — Gestion des Vins</title>
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

.carte-stat{ padding:1.15rem 1.25rem; display:flex; gap:.9rem; }

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

.carte-stat .valeur-stat{ font-size:1.5rem; font-weight:800; line-height:1.15; margin-top:.1rem; }

.entete-carte{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:1rem 1.25rem;
    border-bottom:1px solid var(--bordure);
    gap:.75rem;
    flex-wrap:wrap;
}

.entete-carte h6{ font-weight:700; margin:0; font-size:.92rem; }
.entete-carte a{ font-size:.78rem; font-weight:600; color:var(--bleu); text-decoration:none; }

.corps-carte{ padding:1.1rem 1.25rem; }

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

.ligne-legende{ display:flex; align-items:center; justify-content:space-between; padding:.4rem 0; font-size:.82rem; }
.puce-legende{ width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:.5rem; }
.ligne-legende .valeur-legende{ font-weight:700; color:var(--texte); }

.item-top-vin{ margin-bottom:.85rem; }
.item-top-vin .en-tete-top{ display:flex; justify-content:space-between; font-size:.83rem; margin-bottom:.3rem; }
.item-top-vin .en-tete-top .rang-vin{ font-weight:700; color:var(--texte-att); margin-right:.4rem; }
.barre-progression-fine{ height:7px; border-radius:99px; background:var(--bordure); overflow:hidden; }
.barre-progression-fine span{ display:block; height:100%; border-radius:99px; background:var(--bleu); }

/* ---------- Spécifique Rapports ---------- */

.filtre-periode{
    display:flex;
    gap:.4rem;
    flex-wrap:wrap;
    margin-bottom:1.5rem;
}

.filtre-periode a{
    padding:.45rem .95rem;
    border-radius:99px;
    background:#fff;
    border:1px solid var(--bordure);
    color:var(--texte-att);
    text-decoration:none;
    font-size:.82rem;
    font-weight:600;
}

.filtre-periode a.actif{
    background:var(--navy);
    border-color:var(--navy);
    color:#fff;
}

.bouton-export{
    display:inline-flex;
    align-items:center;
    gap:.35rem;
    font-size:.76rem;
    font-weight:600;
    color:var(--bleu);
    text-decoration:none;
    background:var(--bleu-clair);
    padding:.35rem .7rem;
    border-radius:8px;
}
.bouton-export:hover{ background:#dbe8ff; color:var(--bleu); }

.puce-couleur-vin{ width:9px; height:9px; border-radius:50%; display:inline-block; margin-right:.4rem; }

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
        <a href="rapports.php" class="lien-nav actif"><i class="bi bi-file-earmark-bar-graph"></i> Rapports</a>
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
            <i class="bi bi-file-earmark-bar-graph fs-4 text-primary"></i>
            <div>
                <h2>Rapports</h2>
                <p>Analyse détaillée de l'activité de la cave</p>
            </div>
        </div>

        <?php if($erreur_chargement): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-octagon-fill mt-1"></i>
            <div>
                <strong>Certaines données n'ont pas pu être chargées.</strong>
                Vérifie le nom des tables/colonnes en base.
                <div class="small text-muted mt-1"><?php echo htmlspecialchars($erreur_chargement); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== Filtre de période ===== -->
        <div class="filtre-periode">
            <?php foreach($periodes_valides as $cle => $libelle): ?>
            <a href="?periode=<?php echo $cle; ?>" class="<?php echo $periode === $cle ? 'actif' : ''; ?>"><?php echo $libelle; ?></a>
            <?php endforeach; ?>
        </div>

        <!-- ===== KPIs ===== -->
        <div class="row g-3 mb-3">

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--vert);"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <div class="libelle-stat">Chiffre d'affaires</div>
                        <div class="valeur-stat"><?php echo number_format($ca_periode, 0, ',', ' '); ?> F</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--bleu);"><i class="bi bi-cart-check"></i></div>
                    <div>
                        <div class="libelle-stat">Commandes</div>
                        <div class="valeur-stat"><?php echo number_format($commandes_periode, 0, ',', ' '); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--violet);"><i class="bi bi-basket3"></i></div>
                    <div>
                        <div class="libelle-stat">Panier moyen</div>
                        <div class="valeur-stat"><?php echo number_format($panier_moyen, 0, ',', ' '); ?> F</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-xl-3">
                <div class="carte carte-stat h-100">
                    <div class="icone-stat" style="background:var(--jaune);"><i class="bi bi-person-plus"></i></div>
                    <div>
                        <div class="libelle-stat">Nouveaux clients</div>
                        <div class="valeur-stat"><?php echo number_format($nouveaux_clients, 0, ',', ' '); ?></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== Évolution + Répartition statut + Catégories ===== -->
        <div class="row g-3 mb-3">

            <div class="col-lg-5">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Évolution du chiffre d'affaires</h6>
                    </div>
                    <div class="corps-carte">
                        <canvas id="graphiqueEvolutionRapport" height="220"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Commandes par statut</h6>
                    </div>
                    <div class="corps-carte">
                        <canvas id="graphiqueStatutCmd" height="180"></canvas>
                        <div class="mt-3">
                            <?php $total_statut = array_sum($valeurs_statut_cmd); ?>
                            <?php foreach($labels_statut_cmd as $i => $label): $pct = $total_statut > 0 ? round(($valeurs_statut_cmd[$i] / $total_statut) * 100) : 0; ?>
                            <div class="ligne-legende">
                                <span><span class="puce-legende" style="background:<?php echo $couleurs_statut_cmd[$i]; ?>;"></span><?php echo htmlspecialchars($label); ?></span>
                                <span class="valeur-legende"><?php echo $pct; ?>%</span>
                            </div>
                            <?php endforeach; ?>
                            <?php if(empty($labels_statut_cmd)): ?>
                            <p class="text-muted small mb-0">Aucune commande sur cette période.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Chiffre d'affaires par catégorie</h6>
                    </div>
                    <div class="corps-carte">
                        <canvas id="graphiqueCategories" height="220"></canvas>
                        <?php if(empty($labels_categorie)): ?>
                        <p class="text-muted small mb-0 mt-2">Aucune vente sur cette période.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== Top vins + Top clients ===== -->
        <div class="row g-3 mb-3">

            <div class="col-lg-7">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Top des vins vendus</h6>
                        <a class="bouton-export" href="?periode=<?php echo $periode; ?>&amp;export=top_vins"><i class="bi bi-download"></i> Exporter CSV</a>
                    </div>
                    <div class="corps-carte">
                        <div class="table-responsive">
                        <table class="table-vins">
                            <thead>
                                <tr>
                                    <th>Vin</th>
                                    <th>Couleur</th>
                                    <th>Qté vendue</th>
                                    <th>Chiffre d'affaires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($top_vins)): ?>
                                <tr><td colspan="4" class="text-muted text-center py-3">Aucune vente sur cette période.</td></tr>
                                <?php endif; ?>
                                <?php foreach($top_vins as $v): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($v['nom_vin']); ?></td>
                                    <td><?php echo htmlspecialchars($v['couleur']); ?></td>
                                    <td><?php echo (int)$v['quantite_vendue']; ?></td>
                                    <td><?php echo number_format($v['chiffre_affaires'], 0, ',', ' '); ?> F</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Top des clients</h6>
                        <a class="bouton-export" href="?periode=<?php echo $periode; ?>&amp;export=top_clients"><i class="bi bi-download"></i> Exporter CSV</a>
                    </div>
                    <div class="corps-carte">
                        <?php if(empty($top_clients)): ?>
                        <p class="text-muted small mb-0">Aucun client sur cette période.</p>
                        <?php endif; ?>
                        <?php foreach($top_clients as $i => $c): $max = $top_clients[0]['total_depense'] ?: 1; $pct = round(($c['total_depense'] / $max) * 100); ?>
                        <div class="item-top-vin">
                            <div class="en-tete-top">
                                <span><span class="rang-vin"><?php echo $i + 1; ?></span><?php echo htmlspecialchars($c['prenom'] . ' ' . $c['nom']); ?></span>
                                <span class="fw-semibold"><?php echo number_format($c['total_depense'], 0, ',', ' '); ?> F</span>
                            </div>
                            <div class="barre-progression-fine"><span style="width:<?php echo $pct; ?>%;"></span></div>
                            <div class="text-muted small mt-1"><?php echo (int)$c['nb_commandes']; ?> commande<?php echo $c['nb_commandes'] > 1 ? 's' : ''; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== Mouvements de stock ===== -->
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="carte h-100">
                    <div class="entete-carte">
                        <h6>Derniers mouvements de stock</h6>
                        <a href="../stock/liste_mouvement.php">Voir tout</a>
                    </div>
                    <div class="corps-carte">
                        <div class="table-responsive">
                        <table class="table-vins">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vin</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Stock après</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($mouvements_stock)): ?>
                                <tr><td colspan="5" class="text-muted text-center py-3">Aucun mouvement sur cette période.</td></tr>
                                <?php endif; ?>
                                <?php foreach($mouvements_stock as $m): ?>
                                <tr>
                                    <td class="text-muted"><?php echo date('d/m/Y H:i', strtotime($m['date_mouvement'])); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($m['nom_vin']); ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?php echo $m['type_mouvement'] === 'Entrée' ? 'text-bg-success' : 'text-bg-danger'; ?>">
                                            <?php echo htmlspecialchars($m['type_mouvement']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo (int)$m['quantite']; ?></td>
                                    <td><?php echo (int)$m['stock_apres']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>

new Chart(document.getElementById('graphiqueEvolutionRapport'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels_evolution); ?>,
        datasets: [{
            label: 'Chiffre d\'affaires (F)',
            data: <?php echo json_encode($montants_evolution); ?>,
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,.08)',
            tension: .35,
            fill: true,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

new Chart(document.getElementById('graphiqueStatutCmd'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($labels_statut_cmd); ?>,
        datasets: [{
            data: <?php echo json_encode($valeurs_statut_cmd); ?>,
            backgroundColor: <?php echo json_encode($couleurs_statut_cmd); ?>,
            borderWidth: 0
        }]
    },
    options: { responsive: true, cutout: '68%', plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('graphiqueCategories'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($labels_categorie); ?>,
        datasets: [{
            label: 'Chiffre d\'affaires (F)',
            data: <?php echo json_encode($valeurs_categorie); ?>,
            backgroundColor: '#2f6fed',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true } }
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

</body>
</html>
