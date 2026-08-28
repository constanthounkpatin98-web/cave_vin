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
// Récupération des vins (même requête que l'original)
//===============================================

$erreur_chargement = null;
$requete = null;

try
{
    $requete = $connexion->query("
        SELECT vin.*, categorie.libelle AS libelle_categorie
        FROM vin
        LEFT JOIN categorie ON vin.id_categorie = categorie.id_categorie
        ORDER BY vin.nom_vin ASC
    ");
}
catch(PDOException $e)
{
    $erreur_chargement = $e->getMessage();
}


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

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vins — Gestion des Vins</title>
<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
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
    gap:.75rem;
    flex-wrap:wrap;
}

.entete-carte h6{ font-weight:700; margin:0; font-size:.92rem; }

.corps-carte{ padding:1.1rem 1.25rem; }

.bouton-nouveau{
    background:var(--bleu);
    border-color:var(--bleu);
    font-weight:600;
    font-size:.85rem;
}
.bouton-nouveau:hover{ background:#1f5adf; border-color:#1f5adf; }

.photo-vin-mini{
    width:44px; height:44px;
    object-fit:contain;
    border-radius:8px;
    border:1px solid var(--bordure);
    background:var(--fond);
}

.pas-de-photo{
    width:44px; height:44px;
    border-radius:8px;
    background:var(--fond);
    border:1px dashed var(--bordure);
    display:flex; align-items:center; justify-content:center;
    color:var(--texte-att);
    font-size:1rem;
}

.actions-ligne{
    display:flex;
    flex-wrap:nowrap;
    gap:.35rem;
}
.actions-ligne .btn{ white-space:nowrap; }

/* la colonne Actions ne doit jamais casser sur 2 lignes, même au zoom :
   si la table manque de place, elle défile horizontalement (table-responsive)
   plutôt que de faire passer les boutons à la ligne. */
#tableau td:last-child,
#tableau th:last-child{ white-space:nowrap; }

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
        <a href="../tableau_bord/tableau_bord.php" class="lien-nav"><i class="bi bi-grid-1x2-fill"></i> Tableau de bord</a>
        <a href="../tableau_bord/liste_client.php" class="lien-nav"><i class="bi bi-people"></i> Clients</a>
        <a href="../commande/liste_commande.php" class="lien-nav"><i class="bi bi-bag-check"></i> Commandes</a>
        <a href="../paiement/liste_paiement.php" class="lien-nav"><i class="bi bi-credit-card"></i> Paiements</a>
        <a href="../livraison/liste_livraison.php" class="lien-nav"><i class="bi bi-truck"></i> Livraisons</a>
        <a href="liste_vin.php" class="lien-nav actif"><i class="bi bi-cup-straw"></i> Vins</a>
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

    <div class="zone-corps">

        <div class="entete-page mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-cup-straw fs-4 text-primary"></i>
                <div>
                    <h2>Vins</h2>
                    <p>Gère le catalogue de vins de la cave</p>
                </div>
            </div>
            <a href="ajout_vin.php" class="btn btn-primary bouton-nouveau">
                <i class="bi bi-plus-lg me-1"></i> Nouveau Vin
            </a>
        </div>

        <?php if($erreur_chargement): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-octagon-fill mt-1"></i>
            <div>
                <strong>Les vins n'ont pas pu être chargés.</strong>
                <div class="small text-muted mt-1"><?php echo htmlspecialchars($erreur_chargement); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($_GET["ajout"])): ?>
        <div class="alert alert-success">Le vin a été enregistré avec succès.</div>
        <?php endif; ?>

        <?php if(isset($_GET["modifier"])): ?>
        <div class="alert alert-info">Le vin a été modifié avec succès.</div>
        <?php endif; ?>

        <?php if(isset($_GET["supprimer"])): ?>
        <div class="alert alert-danger">Le vin a été supprimé avec succès.</div>
        <?php endif; ?>

        <div class="carte">
            <div class="entete-carte">
                <h6>Tous les vins</h6>
            </div>
            <div class="corps-carte">
                <div class="table-responsive">
                <table id="tableau" class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Photo</th>
                            <th>Nom du vin</th>
                            <th>Millésime</th>
                            <th>Catégorie</th>
                            <th>Couleur</th>
                            <th>Pays</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($requete): ?>
                        <?php while($ligne = $requete->fetch()): ?>
                        <tr>
                            <td><?php echo $ligne["id_vin"]; ?></td>
                            <td>
                                <?php if(!empty($ligne["photo"])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($ligne["photo"]); ?>" alt="photo" class="photo-vin-mini">
                                <?php else: ?>
                                <div class="pas-de-photo"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($ligne["nom_vin"]); ?></td>
                            <td><?php echo htmlspecialchars($ligne["millesime"]); ?></td>
                            <td><?php echo htmlspecialchars($ligne["libelle_categorie"]); ?></td>
                            <td><?php echo htmlspecialchars($ligne["couleur"]); ?></td>
                            <td><?php echo htmlspecialchars($ligne["pays_origine"]); ?></td>
                            <td><?php echo number_format($ligne["prix"], 0, ',', ' '); ?> FCFA</td>
                            <td>
                                <?php echo $ligne["quantite_stock"]; ?>
                                <?php if($ligne["quantite_stock"] <= 10): ?>
                                <span class="badge rounded-pill text-bg-warning ms-1">Faible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?php echo $ligne["statut"] === 'Disponible' ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($ligne["statut"]); ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <div class="actions-ligne">
                                    <a href="modifier_vin.php?id_vin=<?php echo $ligne["id_vin"]; ?>" class="btn btn-warning btn-sm">Modifier</a>
                                    <a href="supprimer_vin.php?id_vin=<?php echo $ligne["id_vin"]; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Voulez-vous vraiment supprimer ce vin ?');">Supprimer</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
        responsive:true,
        pageLength:10,
        language:{ url:"https://cdn.datatables.net/plug-ins/1.13.8/i18n/fr-FR.json" },
        dom:'Bfrtip',
        buttons:['excel','pdf','print']
    });
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