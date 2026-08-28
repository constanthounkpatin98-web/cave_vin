<?php

require_once("../connexion.php");

if(isset($_GET["id_commande"]))
{
    $id_commande = $_GET["id_commande"];

    // Grâce à ON DELETE CASCADE, les lignes_commande, paiement et livraison liés sont supprimés automatiquement

    $requete = $connexion->prepare("DELETE FROM commande WHERE id_commande = ?");
    $requete->execute([$id_commande]);

    header("Location: liste_commande.php?supprimer=ok");
    exit();
}
else
{
    header("Location: liste_commande.php");
    exit();
}
