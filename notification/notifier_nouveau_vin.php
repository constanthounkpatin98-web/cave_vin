<?php

require_once __DIR__ . "/envoyer_sms.php";
require_once __DIR__ . "/envoyer_email.php";


//===============================================================
// Prévient TOUS les clients inscrits qu'un nouveau vin est
// disponible : SMS + Email + notification interne (la cloche).
//
// À appeler juste après l'ajout réussi d'un vin en base,
// depuis ta page d'administration (ex: admin/ajouter_vin.php).
//
// $connexion doit déjà exister (require_once("../connexion.php")
// dans le fichier appelant, comme partout ailleurs sur le site).
//
// @param array $vin  ["id_vin" => ..., "nom_vin" => ..., "prix" => ...]
//===============================================================

function notifierClientsNouveauVin($vin)
{
    global $connexion;

    //-----------------------------------------------------------
    // Récupération de tous les clients
    // (adapte les noms de colonnes ci-dessous si les tiens
    //  sont différents dans ta table `client`)
    //-----------------------------------------------------------

    $requete = $connexion->query("
        SELECT id_client, nom, prenom, telephone, email
        FROM client
    ");

    $clients = $requete->fetchAll();

    $nomVin = $vin["nom_vin"];
    $prix   = number_format($vin["prix"], 0, ',', ' ');


    //-----------------------------------------------------------
    // Contenu des messages
    //-----------------------------------------------------------

    $messageSMS = "Cave à Vins : \"$nomVin\" ($prix FCFA) vient d'arriver en cave ! Allez voir votre panier / notre boutique en ligne.";

    $sujetEmail = "🍷 Nouveau vin disponible : $nomVin";

    $corpsEmail = "
        <div style='font-family:Poppins,Arial,sans-serif; color:#2c2622; max-width:520px; margin:0 auto;'>

            <div style='background:#2b2725; padding:22px; text-align:center; border-bottom:3px solid #6d1626;'>
                <span style='color:#fff; font-size:1.3rem; font-weight:700;'>🍷 CAVE À VINS</span>
            </div>

            <div style='padding:28px 24px;'>

                <h2 style='color:#6d1626; margin-top:0;'>Un nouveau vin vient d'arriver !</h2>

                <p style='font-size:1rem; line-height:1.6;'>
                    <strong>" . htmlspecialchars($nomVin) . "</strong> est maintenant disponible
                    à <strong>$prix FCFA</strong>.
                </p>

                <p style='font-size:0.95rem; line-height:1.6; color:#4a423c;'>
                    Connectez-vous à votre espace pour le découvrir et l'ajouter à votre panier
                    avant qu'il ne soit épuisé.
                </p>

                <div style='text-align:center; margin:28px 0;'>
                    <a href='" . URL_SITE . "'
                       style='background:#6d1626; color:#fff; padding:12px 26px; border-radius:6px;
                              text-decoration:none; font-weight:600; display:inline-block;'>
                        Voir le vin
                    </a>
                </div>

                <hr style='border:none; border-top:1px solid #efe8de; margin:24px 0;'>

                <p style='font-size:0.78rem; color:#8a8078; text-align:center;'>
                    Cave à Vins — Votre cave en ligne au Bénin
                </p>

            </div>

        </div>
    ";


    //-----------------------------------------------------------
    // Envoi à chaque client
    //-----------------------------------------------------------

    foreach ($clients as $client) {

        // ----- SMS -----
        if (!empty($client["telephone"])) {
            envoyerSMS($client["telephone"], $messageSMS);
        }

        // ----- Email -----
        if (!empty($client["email"])) {
            envoyerEmail(
                $client["email"],
                trim(($client["prenom"] ?? "") . " " . ($client["nom"] ?? "")),
                $sujetEmail,
                $corpsEmail
            );
        }

        // ----- Notification interne (cloche du site) -----
        try {

            $requete_notif = $connexion->prepare("
                INSERT INTO notification (id_client, titre, message, statut)
                VALUES (?, ?, ?, 'Non lue')
            ");

            $requete_notif->execute([
                $client["id_client"],
                "Nouveau vin disponible",
                $nomVin . " est maintenant disponible à " . $prix . " FCFA. Découvrez-le et ajoutez-le à votre panier !"
            ]);

        } catch (PDOException $e) {

            error_log("Erreur notification interne (nouveau vin) : " . $e->getMessage());

        }
    }
}