<?php

session_start();

require_once("../connexion.php");

//===============================================
// Vérification du panier
//===============================================

if(!isset($_SESSION["panier"]) || count($_SESSION["panier"]) == 0)
{
    header("Location: ../panier/panier.php");
    exit();
}

$panier    = $_SESSION["panier"];
$id_client = null;

//===============================================
// Récupération des données du formulaire
//===============================================

$mode_livraison    = trim($_POST["mode_livraison"] ?? "Standard");

//===============================================
// Gestion du client
//===============================================

if(isset($_SESSION["client_id"]))
{
    // Client déjà connecté
    $id_client = $_SESSION["client_id"];
    $adresse_livraison = trim($_POST["adresse_livraison"] ?? "");
}
else
{
    // Client non connecté - on crée ou récupère le compte
    $nom        = trim($_POST["nom"] ?? "");
    $prenom     = trim($_POST["prenom"] ?? "");
    $email      = trim($_POST["email"] ?? "");
    $telephone  = trim($_POST["telephone"] ?? "");
    $adresse    = trim($_POST["adresse"] ?? "");
    $mot_de_passe = $_POST["mot_de_passe"] ?? "";
    $adresse_livraison = $adresse;

    // Validation des champs obligatoires
    if(empty($nom) || empty($prenom) || empty($email) || empty($adresse) || empty($mot_de_passe))
    {
        header("Location: ../panier/valider_panier.php?erreur=champs_obligatoires");
        exit();
    }

    if(strlen($mot_de_passe) < 8)
    {
        header("Location: ../panier/valider_panier.php?erreur=mdp_court");
        exit();
    }

    try
    {
        //===============================================
        // Vérifier si le client existe déjà par email
        //===============================================

        $requete_verif = $connexion->prepare("SELECT * FROM client WHERE email = ?");
        $requete_verif->execute([$email]);
        $client_existant = $requete_verif->fetch();

        if($client_existant)
        {
            // Client existe déjà - on le connecte
            $id_client = $client_existant["id_client"];

            // Mettre à jour les informations si elles ont changé
            if(empty($client_existant["telephone"]) && !empty($telephone))
            {
                $req_update = $connexion->prepare("UPDATE client SET telephone = ? WHERE id_client = ?");
                $req_update->execute([$telephone, $id_client]);
            }
            if(empty($client_existant["adresse"]) && !empty($adresse))
            {
                $req_update = $connexion->prepare("UPDATE client SET adresse = ? WHERE id_client = ?");
                $req_update->execute([$adresse, $id_client]);
            }

            // Créer la session
            $_SESSION["client_id"] = $id_client;
            $_SESSION["client_nom"] = $client_existant["nom"] . " " . $client_existant["prenom"];
        }
        else
        {
            //===============================================
            // Création automatique du nouveau client
            //===============================================

            $mot_de_passe_hache = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            $requete_insert = $connexion->prepare("
                INSERT INTO client (nom, prenom, telephone, email, adresse, mot_de_passe, statut)
                VALUES (?, ?, ?, ?, ?, ?, 'Actif')
            ");
            $requete_insert->execute([$nom, $prenom, $telephone, $email, $adresse, $mot_de_passe_hache]);

            $id_client = $connexion->lastInsertId();

            // Créer la session
            $_SESSION["client_id"] = $id_client;
            $_SESSION["client_nom"] = $nom . " " . $prenom;
        }
    }
    catch(PDOException $e)
    {
        header("Location: ../panier/valider_panier.php?erreur=base&msg=" . urlencode($e->getMessage()));
        exit();
    }
}

//===============================================
// Vérification finale du client
//===============================================

if(!$id_client)
{
    header("Location: ../panier/valider_panier.php?erreur=client");
    exit();
}

//===============================================
// Création de la commande
//===============================================

try
{
    $connexion->beginTransaction();

    // Calcul du montant total
    $montant_total = 0;
    $details = [];

    foreach($panier as $id_vin => $quantite)
    {
        $requete_vin = $connexion->prepare("SELECT * FROM vin WHERE id_vin = ?");
        $requete_vin->execute([$id_vin]);
        $vin = $requete_vin->fetch();

        if(!$vin || $vin["quantite_stock"] < $quantite)
        {
            throw new Exception("Stock insuffisant pour le produit : " . ($vin["nom_vin"] ?? "inconnu"));
        }

        if($vin["statut"] != "Disponible")
        {
            throw new Exception("Le produit n'est plus disponible : " . ($vin["nom_vin"] ?? "inconnu"));
        }

        $sous_total = $vin["prix"] * $quantite;
        $montant_total += $sous_total;

        $details[] = [
            "id_vin"        => $id_vin,
            "quantite"      => $quantite,
            "prix_unitaire" => $vin["prix"],
            "sous_total"    => $sous_total,
        ];
    }

    //===============================================
    // Insertion de la commande
    //===============================================

    $requete_commande = $connexion->prepare("
        INSERT INTO commande (montant_total, statut, mode_livraison, id_client, date_commande)
        VALUES (?, 'En attente', ?, ?, NOW())
    ");
    $requete_commande->execute([$montant_total, $mode_livraison, $id_client]);

    $id_commande = $connexion->lastInsertId();

    //===============================================
    // Insertion des lignes de commande + mise à jour stock
    //===============================================

    $requete_ligne = $connexion->prepare("
        INSERT INTO ligne_commande (quantite, prix_unitaire, sous_total, id_commande, id_vin)
        VALUES (?, ?, ?, ?, ?)
    ");

    $requete_stock = $connexion->prepare("
        UPDATE vin SET quantite_stock = quantite_stock - ? WHERE id_vin = ?
    ");

    $requete_mouvement = $connexion->prepare("
        INSERT INTO stock_mouvement (type_mouvement, quantite, stock_apres, id_vin)
        VALUES ('Sortie', ?, (SELECT quantite_stock FROM (SELECT quantite_stock FROM vin WHERE id_vin = ?) AS t), ?)
    ");

    foreach($details as $detail)
    {
        $requete_ligne->execute([
            $detail["quantite"],
            $detail["prix_unitaire"],
            $detail["sous_total"],
            $id_commande,
            $detail["id_vin"]
        ]);

        $requete_stock->execute([$detail["quantite"], $detail["id_vin"]]);

        $requete_mouvement->execute([$detail["quantite"], $detail["id_vin"], $detail["id_vin"]]);
    }

    //===============================================
    // Création de la livraison
    //===============================================

    // Calcul des frais de livraison
    $frais_livraison = 0;
    if($mode_livraison == "Express")
    {
        $frais_livraison = 2000;
    }
    elseif($mode_livraison == "Standard")
    {
        $frais_livraison = 1000;
    }

    $requete_livraison = $connexion->prepare("
        INSERT INTO livraison (adresse_livraison, statut, frais_livraison, id_commande)
        VALUES (?, 'En préparation', ?, ?)
    ");
    $requete_livraison->execute([$adresse_livraison, $frais_livraison, $id_commande]);

    $connexion->commit();

    //===============================================
    // Vider le panier
    //===============================================

    unset($_SESSION["panier"]);

    //===============================================
    // Redirection vers la confirmation
    //===============================================

    header("Location: ../panier/confirmation_commande.php?id_commande=" . $id_commande);
    exit();
}
catch(Exception $e)
{
    $connexion->rollBack();
    header("Location: ../panier/valider_panier.php?erreur=commande&msg=" . urlencode($e->getMessage()));
    exit();
}