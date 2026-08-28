<?php

session_start();

require_once("../connexion.php");


// ================================================================
// SÉCURITÉ
// ================================================================

if (
    !isset($_SESSION["client_id"])
) {

    header(
        "Location: ../client/connexion_client.php"
    );

    exit();
}


if (
    !isset($_GET["id_commande"])
) {

    header(
        "Location: ../client/accueil_client.php"
    );

    exit();
}


$id_commande =
    (int) $_GET["id_commande"];


$id_client =
    $_SESSION["client_id"];


// ================================================================
// RÉCUPÉRER COMMANDE
// ================================================================

$requete_commande =
    $connexion->prepare("
        SELECT *
        FROM commande
        WHERE id_commande = ?
        AND id_client = ?
        LIMIT 1
    ");


$requete_commande->execute([

    $id_commande,

    $id_client

]);


$commande =
    $requete_commande->fetch(
        PDO::FETCH_ASSOC
    );


if (!$commande) {

    header(
        "Location: ../client/accueil_client.php"
    );

    exit();
}


// ================================================================
// CLIENT
// ================================================================

$requete_client =
    $connexion->prepare("
        SELECT *
        FROM client
        WHERE id_client = ?
        LIMIT 1
    ");


$requete_client->execute([
    $id_client
]);


$client =
    $requete_client->fetch(
        PDO::FETCH_ASSOC
    );


// ================================================================
// ARTICLES
// ================================================================

$requete_lignes =
    $connexion->prepare("
        SELECT
            ligne_commande.*,
            vin.nom_vin,
            vin.photo
        FROM ligne_commande

        INNER JOIN vin
            ON ligne_commande.id_vin =
               vin.id_vin

        WHERE ligne_commande.id_commande = ?
    ");


$requete_lignes->execute([
    $id_commande
]);


$lignes =
    $requete_lignes->fetchAll(
        PDO::FETCH_ASSOC
    );


// ================================================================
// PAIEMENT
// ================================================================

$requete_paiement =
    $connexion->prepare("
        SELECT *
        FROM paiement
        WHERE id_commande = ?
        LIMIT 1
    ");


$requete_paiement->execute([
    $id_commande
]);


$paiement =
    $requete_paiement->fetch(
        PDO::FETCH_ASSOC
    );


// ================================================================
// LIVRAISON
// ================================================================

$requete_livraison =
    $connexion->prepare("
        SELECT *
        FROM livraison
        WHERE id_commande = ?
        LIMIT 1
    ");


$requete_livraison->execute([
    $id_commande
]);


$livraison =
    $requete_livraison->fetch(
        PDO::FETCH_ASSOC
    );


// ================================================================
// NUMÉRO COMMANDE
// ================================================================

$numero_commande =

    "CMD-" .

    strtoupper(

        substr(

            md5(

                $id_commande .
                date("Ymd")

            ),

            0,
            8

        )

    );


// ================================================================
// SOUS-TOTAL
// ================================================================

$sous_total = 0;


foreach (
    $lignes as $ligne
) {

    $sous_total +=
        (float) (
            $ligne["sous_total"]
            ?? 0
        );
}


// ================================================================
// LIVRAISON
// ================================================================

$frais_livraison =
    (float) (
        $livraison[
            "frais_livraison"
        ] ?? 0
    );


// ================================================================
// TOTAL
// ================================================================

$total_general =
    $sous_total +
    $frais_livraison;


// ================================================================
// DATE COMMANDE
// ================================================================

$date_commande = "";


if (
    !empty(
        $commande["date_commande"]
    )
) {

    $date_commande =
        date(
            "d/m/Y à H:i",
            strtotime(
                $commande["date_commande"]
            )
        );
}


// ================================================================
// INFORMATIONS PAIEMENT
// ================================================================

$montant_paye =
    (float) (
        $paiement["montant"]
        ?? $total_general
    );


$date_paiement =
    $paiement["date_paiement"]
    ?? null;


if ($date_paiement) {

    $date_paiement =
        date(
            "d/m/Y à H:i",
            strtotime(
                $date_paiement
            )
        );
}


$mode_paiement =
    $paiement[
        "mode_paiement"
    ]
    ?? "Non défini";


$statut_paiement =
    $paiement[
        "statut"
    ]
    ?? "En attente";


$reference_transaction =
    $paiement[
        "reference_transaction"
    ]
    ?? "Non disponible";

?>
<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>

        Reçu de paiement -
        <?php echo htmlspecialchars(
            $numero_commande
        ); ?>

    </title>


    <link
        rel="stylesheet"
        href="../bootstrap-5.3.8-dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="../bootstrap-icons-1.11.3/font/bootstrap-icons.css"
    >


    <style>

        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;
        }


        body {

            background: #f3efe8;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #1c1a19;

            padding: 40px 20px;
        }


        .page-header {

            text-align: center;

            margin-bottom: 30px;
        }


        .page-logo {

            font-family: Georgia, serif;

            font-size: 1.7rem;

            font-weight: 700;

            color: #1c1a19;
        }


        .page-logo span {

            color: #6d1626;
        }


        .page-titre {

            font-size: 1.7rem;

            font-weight: 800;

            margin-top: 18px;

            color: #1c1a19;
        }


        .recu-container {

            max-width: 500px;

            margin: auto;

            background: #ffffff;

            border-radius: 20px;

            box-shadow:
                0 5px 25px
                rgba(0,0,0,0.06);

            padding: 30px 28px;
        }


        .recu-header {

            text-align: center;

            border-bottom:
                2px solid #6d1626;

            padding-bottom: 20px;

            margin-bottom: 25px;
        }


        .logo {

            font-family: Georgia, serif;

            font-size: 1.9rem;

            font-weight: 700;

            color: #1c1a19;
        }


        .logo span {

            color: #6d1626;
        }


        .sous-titre {

            color: #6a615a;

            font-size: 0.85rem;

            margin-top: 5px;

            letter-spacing: 1px;
        }


        .titre {

            font-size: 1.2rem;

            font-weight: 700;

            margin-top: 15px;
        }


        .numero {

            color: #6d1626;

            font-weight: 700;

            margin-top: 8px;
        }


        .section {

            background: transparent;

            border-radius: 0;

            padding: 0;

            margin-bottom: 22px;
        }


        .section-title {

            font-weight: 700;

            color: #6d1626;

            margin-bottom: 14px;

            font-size: 0.95rem;

            text-transform: uppercase;

            letter-spacing: 0.5px;
        }


        .ligne-info {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 20px;

            padding: 12px 0;

            border-bottom:
                1px solid #eee5dc;
        }


        .ligne-info:last-child {

            border-bottom: none;
        }


        .label {

            font-weight: 700;

            color: #1c1a19;

            font-size: 0.98rem;
        }


        .ligne-info > span:last-child {

            color: #6a615a;

            font-size: 0.98rem;

            text-align: right;
        }


        .badge-statut {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            background: #e8f5e9;

            color: #1f7a45;

            font-weight: 700;

            padding: 4px 12px;

            border-radius: 20px;

            font-size: 0.9rem;
        }


        table {

            width: 100%;

            border-collapse:
                collapse;
        }


        th {

            text-align: left;

            padding: 10px 6px;

            border-bottom:
                2px solid #6d1626;

            font-size: 0.82rem;
        }


        td {

            padding: 10px 6px;

            border-bottom:
                1px solid #eee5dc;

            font-size: 0.9rem;
        }


        .text-right {

            text-align: right;
        }


        .text-center {

            text-align: center;
        }


        .total-box {

            margin-top: 20px;

            border-top:
                2px solid #6d1626;

            padding-top: 15px;
        }


        .total-line {

            display: flex;

            justify-content:
                space-between;

            padding: 6px 0;
        }


        .total-final {

            font-size: 1.2rem;

            font-weight: 700;

            color: #6d1626;

            border-top:
                1px solid #ddd;

            margin-top: 8px;

            padding-top: 12px;
        }


        .confirmation {

            background: #e8f5e9;

            border-left:
                4px solid #1f7a45;

            color: #1f4a2a;

            padding: 15px;

            border-radius: 8px;

            margin: 20px 0;
        }


        .statut-paye {

            color: #1f7a45;

            font-weight: 700;
        }


        .actions {

            display: flex;

            justify-content:
                center;

            gap: 10px;

            margin-top: 30px;

            flex-wrap: nowrap;
        }


        .btn-action {

            text-decoration: none;

            padding: 10px 6px;

            border-radius: 8px;

            font-weight: 600;

            border: 2px solid #6d1626;

            color: #6d1626;

            background: white;

            flex: 1 1 0;

            min-width: 0;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 4px;

            white-space: normal;

            word-break: break-word;

            text-align: center;

            overflow: hidden;

            box-sizing: border-box;

            font-size: 0.78rem;

            line-height: 1.15;
        }


        .btn-action .bi {

            flex-shrink: 0;

            font-size: 1.1rem;
        }


        .btn-action:hover {

            background: #6d1626;

            color: white;
        }


        .btn-print {

            background: #6d1626;

            color: white;
        }


        @media(max-width:576px) {

            .actions {

                gap: 6px;
            }


            .btn-action {

                padding: 8px 4px;

                font-size: 0.65rem;

                gap: 4px;
            }


            .btn-action .bi {

                font-size: 1rem;
            }


            body {

                padding: 15px;
            }


            .recu-container {

                padding: 20px 15px;
            }


            .ligne-info {

                flex-direction:
                    column;

                gap: 2px;
            }


            table {

                font-size:
                    0.75rem;
            }


            th,
            td {

                padding:
                    7px 3px;
            }

        }


        @media(max-width:360px) {

            .btn-action {

                font-size: 0.6rem;

                padding: 7px 2px;
            }


            .btn-action .bi {

                font-size: 0.9rem;
            }
        }


        @media print {

            @page {

                size: A4;

                margin: 10mm;
            }


            html,
            body {

                background: white;

                padding: 0;

                margin: 0;

                font-size: 12px;
            }


            /* Les icônes Bootstrap Icons sont des ligatures de
               police : si la police ne se charge pas au moment
               de l'impression, les caractères bruts débordent
               et créent une 2e page. On les masque simplement
               à l'impression, elles sont purement décoratives. */

            .bi {

                display: none !important;
            }


            .recu-container {

                box-shadow: none;

                max-width: 100%;

                padding: 10px 0;
            }


            .recu-header {

                padding-bottom: 10px;

                margin-bottom: 12px;
            }


            .section {

                padding: 10px 14px;

                margin-bottom: 10px;

                page-break-inside: avoid;
            }


            .ligne-info {

                padding: 4px 0;
            }


            table th,
            table td {

                padding: 6px 4px;

                font-size: 11px;
            }


            .total-box {

                margin-top: 10px;

                padding-top: 8px;

                page-break-inside: avoid;
            }


            .confirmation {

                padding: 8px 12px;

                margin: 10px 0;

                page-break-inside: avoid;
            }


            .actions {

                display: none !important;
            }

        }

    </style>

</head>


<body>


<div class="page-header">

    <div class="page-logo">

        🍷 CAVE <span>À VINS</span>

    </div>


    <div class="page-titre">

        Reçu de Paiement

    </div>

</div>


<div class="recu-container">


    <!-- =====================================================
         EN-TÊTE
    ====================================================== -->

    <div class="recu-header">


        <div class="numero">

            <?php

            echo htmlspecialchars(
                $numero_commande
            );

            ?>

        </div>


        <div style="
            color:#777;
            font-size:0.85rem;
            margin-top:5px;
        ">

            <?php

            echo htmlspecialchars(
                $date_commande
            );

            ?>

        </div>


    </div>


    <!-- =====================================================
         CONFIRMATION
    ====================================================== -->

    <div class="confirmation">

        <i
            class="bi bi-check-circle-fill"
        ></i>

        <strong>
            Paiement confirmé avec succès.
        </strong>

        <br>

        Votre paiement a été enregistré
        automatiquement dans notre système.

    </div>


    <!-- =====================================================
         CLIENT
    ====================================================== -->

    <div class="section">


        <div class="section-title">

            Informations client

        </div>


        <div class="ligne-info">

            <span class="label">
                Client
            </span>

            <span>

                <?php

                echo htmlspecialchars(

                    ($client["prenom"] ?? "") .
                    " " .
                    ($client["nom"] ?? "")

                );

                ?>

            </span>

        </div>


        <div class="ligne-info">

            <span class="label">
                Téléphone
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $client["telephone"]
                    ?? "Non renseigné"
                );

                ?>

            </span>

        </div>


        <div class="ligne-info">

            <span class="label">
                Adresse
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $client["adresse"]
                    ?? "Non renseignée"
                );

                ?>

            </span>

        </div>


    </div>


    <!-- =====================================================
         ARTICLES
    ====================================================== -->

    <div class="section">


        <div class="section-title">

            Articles commandés

        </div>


        <table>


            <thead>

                <tr>

                    <th>
                        Article
                    </th>

                    <th class="text-center">
                        Qté
                    </th>

                    <th class="text-right">
                        Prix
                    </th>

                    <th class="text-right">
                        Total
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php

                foreach (
                    $lignes as $ligne
                ):

                ?>

                    <tr>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $ligne["nom_vin"]
                                ?? "Vin"
                            );

                            ?>

                        </td>


                        <td class="text-center">

                            <?php

                            echo (int) (
                                $ligne["quantite"]
                                ?? 0
                            );

                            ?>

                        </td>


                        <td class="text-right">

                            <?php

                            echo number_format(

                                (float) (
                                    $ligne[
                                        "prix_unitaire"
                                    ] ?? 0
                                ),

                                0,

                                ",",

                                " "

                            );

                            ?>

                            FCFA

                        </td>


                        <td class="text-right">

                            <?php

                            echo number_format(

                                (float) (
                                    $ligne[
                                        "sous_total"
                                    ] ?? 0
                                ),

                                0,

                                ",",

                                " "

                            );

                            ?>

                            FCFA

                        </td>


                    </tr>


                <?php

                endforeach;

                ?>


            </tbody>


        </table>


    </div>


    <!-- =====================================================
         TOTAL
    ====================================================== -->

    <div class="total-box">


        <div class="total-line">

            <span>
                Sous-total
            </span>

            <span>

                <?php

                echo number_format(
                    $sous_total,
                    0,
                    ",",
                    " "
                );

                ?>

                FCFA

            </span>

        </div>


        <?php if (
            $frais_livraison > 0
        ): ?>


            <div class="total-line">

                <span>
                    Frais de livraison
                </span>

                <span>

                    <?php

                    echo number_format(
                        $frais_livraison,
                        0,
                        ",",
                        " "
                    );

                    ?>

                    FCFA

                </span>

            </div>


        <?php endif; ?>


        <div class="total-line total-final">

            <span>
                TOTAL PAYÉ
            </span>

            <span>

                <?php

                echo number_format(
                    $montant_paye,
                    0,
                    ",",
                    " "
                );

                ?>

                FCFA

            </span>

        </div>


    </div>


    <!-- =====================================================
         INFORMATIONS PAIEMENT
    ====================================================== -->

    <div class="section"
         style="margin-top:25px;">

        <div class="section-title">

            Informations du paiement

        </div>


        <div class="ligne-info">

            <span class="label">
                Mode de paiement
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $mode_paiement
                );

                ?>

            </span>

        </div>


        <div class="ligne-info">

            <span class="label">
                Statut
            </span>

            <span class="badge-statut">

                ✅

                <?php

                echo htmlspecialchars(
                    $statut_paiement
                );

                ?>

            </span>

        </div>


        <div class="ligne-info">

            <span class="label">
                Référence transaction
            </span>

            <span style="
                word-break:break-all;
            ">

                <?php

                echo htmlspecialchars(
                    $reference_transaction
                );

                ?>

            </span>

        </div>


        <?php if (
            !empty($date_paiement)
        ): ?>

            <div class="ligne-info">

                <span class="label">
                    Date du paiement
                </span>

                <span>

                    <?php

                    echo htmlspecialchars(
                        $date_paiement
                    );

                    ?>

                </span>

            </div>

        <?php endif; ?>


    </div>


    <!-- =====================================================
         LIVRAISON
    ====================================================== -->

    <div class="section">

        <div class="section-title">

            Livraison

        </div>


        <div class="ligne-info">

            <span class="label">
                Mode de livraison
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $commande[
                        "mode_livraison"
                    ]
                    ?? "Standard"
                );

                ?>

            </span>

        </div>


        <div class="ligne-info">

            <span class="label">
                Statut commande
            </span>

            <span>

                <?php

                echo htmlspecialchars(
                    $commande["statut"]
                    ?? "Payée"
                );

                ?>

            </span>

        </div>

    </div>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <div style="
        text-align:center;
        color:#9a8f84;
        font-size:0.8rem;
        margin-top:25px;
        padding-top:20px;
        border-top:1px solid #eee5dc;
    ">

        Merci pour votre confiance — Cave à Vins,
        votre cave en ligne au Bénin.

    </div>


    <!-- =====================================================
         ACTIONS
    ====================================================== -->

    <div class="actions">


        <a
            href="../client/accueil_client.php"
            class="btn-action"
        >

            <i
                class="bi bi-arrow-left"
            ></i>

            Retour

        </a>


        <button
            onclick="window.print()"
            class="btn-action btn-print"
        >

            <i
                class="bi bi-printer"
            ></i>

            Imprimer le reçu

        </button>


        <a
            href="../client/mes_commandes.php"
            class="btn-action"
        >

            <i
                class="bi bi-receipt"
            ></i>

            Mes commandes

        </a>


    </div>


</div>


<script
    src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>