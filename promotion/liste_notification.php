<?php

session_start();

require_once("../connexion.php");

if(!isset($_SESSION["admin_id"]))
{
    header("Location: ../administrateur/connexion_admin.php");
    exit();
}


//===============================================
// Récupération des notifications
//===============================================

$requete = $connexion->query("

SELECT notification.*, client.nom AS nom_client, client.prenom AS prenom_client

FROM notification

INNER JOIN client ON notification.id_client = client.id_client

ORDER BY notification.date_envoi DESC

");

?>
<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Notifications Envoyées</title>

<link rel="stylesheet" href="../bootstrap-5.3.8-dist/css/bootstrap.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

<h3 class="mb-0">

Notifications Envoyées

</h3>

<a href="envoyer_notification.php"

class="btn btn-success">

Nouvelle Notification

</a>

</div>

<div class="card-body">


<?php

if(isset($_GET["envoi"]))
{

?>

<div class="alert alert-success">

La notification a été envoyée avec succès.

</div>

<?php

}

?>


<?php

if(isset($_GET["supprimer"]))
{

?>

<div class="alert alert-danger">

La notification a été supprimée avec succès.

</div>

<?php

}

?>


<table

id="tableau"

class="table table-bordered table-striped table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Client</th>

<th>Titre</th>

<th>Message</th>

<th>Date envoi</th>

<th>Statut</th>

<th width="120">

Actions

</th>

</tr>

</thead>

<tbody>

<?php

while($ligne = $requete->fetch())

{

?>

<tr>

<td>

<?php echo $ligne["id_notification"]; ?>

</td>

<td>

<?php echo htmlspecialchars($ligne["nom_client"] . " " . $ligne["prenom_client"]); ?>

</td>

<td>

<?php echo htmlspecialchars($ligne["titre"]); ?>

</td>

<td>

<?php echo htmlspecialchars($ligne["message"]); ?>

</td>

<td>

<?php echo htmlspecialchars($ligne["date_envoi"]); ?>

</td>

<td>

<?php echo htmlspecialchars($ligne["statut"]); ?>

</td>

<td>

<a href="supprimer_notification.php?id_notification=<?php echo $ligne["id_notification"]; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Voulez-vous vraiment supprimer cette notification ?');">Supprimer</a>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

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

language:{

url:"https://cdn.datatables.net/plug-ins/1.13.8/i18n/fr-FR.json"

},

dom:'Bfrtip',

buttons:[

'excel',

'pdf',

'print'

]

});

});

</script>

</body>

</html>
