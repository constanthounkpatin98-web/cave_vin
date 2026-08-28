<?php

session_start();

require_once("../connexion.php");

// ================================================================
// CONFIGURATION KKiaPay
// ================================================================

const KKIAPAY_PUBLIC_KEY = "567eb6b09c8e11f184146d9578b06a24";
const KKIAPAY_SANDBOX = true;


// ================================================================
// RÉCUPÉRER ID COMMANDE
// ================================================================

$id_commande = isset($_GET["id_commande"])
    ? (int) $_GET["id_commande"]
    : 0;

if (!$id_commande) {

    header("Location: ../client/accueil_client.php");
    exit();
}


// ================================================================
// RÉCUPÉRER LA COMMANDE
// ================================================================

$requete_commande = $connexion->prepare("
    SELECT *
    FROM commande
    WHERE id_commande = ?
    LIMIT 1
");

$requete_commande->execute([
    $id_commande
]);

$commande = $requete_commande->fetch(PDO::FETCH_ASSOC);

if (!$commande) {

    header("Location: ../client/accueil_client.php");
    exit();
}


// ================================================================
// RÉCUPÉRER LES LIGNES DE COMMANDE
// ================================================================

$requete_lignes = $connexion->prepare("
    SELECT
        ligne_commande.*,
        vin.nom_vin
    FROM ligne_commande
    LEFT JOIN vin
        ON ligne_commande.id_vin = vin.id_vin
    WHERE ligne_commande.id_commande = ?
");

$requete_lignes->execute([
    $id_commande
]);

$lignes = $requete_lignes->fetchAll(PDO::FETCH_ASSOC);


// ================================================================
// RÉCUPÉRER LA LIVRAISON
// ================================================================

$requete_livraison = $connexion->prepare("
    SELECT *
    FROM livraison
    WHERE id_commande = ?
    LIMIT 1
");

$requete_livraison->execute([
    $id_commande
]);

$livraison = $requete_livraison->fetch(PDO::FETCH_ASSOC);


// ================================================================
// RÉCUPÉRER LE PAIEMENT
// ================================================================

$requete_paiement = $connexion->prepare("
    SELECT *
    FROM paiement
    WHERE id_commande = ?
    LIMIT 1
");

$requete_paiement->execute([
    $id_commande
]);

$paiement = $requete_paiement->fetch(PDO::FETCH_ASSOC);


// ================================================================
// RÉCUPÉRER LE CLIENT
// ================================================================

$client = null;

if (isset($_SESSION["client_id"])) {

    $requete_client = $connexion->prepare("
        SELECT *
        FROM client
        WHERE id_client = ?
        LIMIT 1
    ");

    $requete_client->execute([
        $_SESSION["client_id"]
    ]);

    $client = $requete_client->fetch(PDO::FETCH_ASSOC);
}


// ================================================================
// CALCUL DU SOUS-TOTAL
// ================================================================

$sous_total = 0;

foreach ($lignes as $ligne) {

    $sous_total += (float) ($ligne["sous_total"] ?? 0);
}


// ================================================================
// FRAIS DE LIVRAISON
// ================================================================

$frais_livraison = (float) (
    $livraison["frais_livraison"] ?? 0
);


// ================================================================
// TOTAL FINAL
// ================================================================

$total_general =
    $sous_total + $frais_livraison;


// ================================================================
// STATUT PAIEMENT
// ================================================================

$statut_paiement = strtolower(
    trim(
        (string) (
            $paiement["statut"] ?? "en attente"
        )
    )
);


$est_paye = in_array(
    $statut_paiement,
    [
        "payé",
        "paye",
        "success",
        "paid",
        "réussi",
        "reussi"
    ],
    true
);


// ================================================================
// NUMÉRO DE COMMANDE
// ================================================================

$numero_commande =
    "CMD-" .
    strtoupper(
        substr(
            md5(
                $id_commande . date("Ymd")
            ),
            0,
            8
        )
    );

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
        Paiement - Cave à Vins
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

        body {

            background: #f6f1ea;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            display: flex;

            justify-content: center;

            align-items: center;

            min-height: 100vh;

            margin: 0;

            padding: 20px;
        }


        .paiement-container {

            max-width: 500px;

            width: 100%;

            background: #ffffff;

            border-radius: 18px;

            padding: 40px 35px;

            box-shadow:
                0 10px 40px
                rgba(0, 0, 0, 0.10);

            text-align: center;
        }


        .logo {

            font-family: Georgia, serif;

            font-size: 1.8rem;

            font-weight: 700;

            color: #1c1a19;

            margin-bottom: 5px;
        }


        .logo span {

            color: #6d1626;
        }


        .sous-titre {

            color: #8a8078;

            font-size: 0.9rem;

            margin-bottom: 25px;
        }


        .separateur {

            border: 0;

            border-top:
                1px solid #efe8de;

            margin: 25px 0;
        }


        .badge-securise {

            display: inline-block;

            background: #e8f5e9;

            color: #1f7a45;

            font-size: 0.75rem;

            padding: 6px 14px;

            border-radius: 20px;

            margin-bottom: 15px;
        }


        .montant-box {

            background: #f8f5f0;

            border-radius: 14px;

            padding: 25px 20px;

            margin: 20px 0;
        }


        .montant-label {

            color: #8a8078;

            font-size: 0.85rem;

            text-transform: uppercase;

            letter-spacing: 1px;
        }


        .montant {

            font-size: 2.8rem;

            font-weight: 800;

            color: #6d1626;

            margin: 5px 0;
        }


        .montant-devise {

            font-size: 1.1rem;

            font-weight: 600;
        }


        .ref-commande {

            color: #8a8078;

            font-size: 0.9rem;

            margin-top: 5px;
        }


        .frais {

            color: #777;

            font-size: 0.85rem;

            margin-top: 8px;
        }


        .btn-kkiapay {

            background: #6d1626;

            border: none;

            color: #ffffff;

            font-weight: 600;

            font-size: 1.05rem;

            padding: 16px 30px;

            border-radius: 10px;

            width: 100%;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            transition: 0.3s;
        }


        .btn-kkiapay:hover {

            background: #4e0f1c;

            transform: translateY(-2px);
        }


        .btn-kkiapay:disabled {

            background: #b98d97;

            cursor: not-allowed;

            transform: none;
        }


        .message {

            margin-top: 15px;

            padding: 13px 16px;

            border-radius: 8px;

            font-size: 0.9rem;

            display: none;

            word-break: break-word;
        }


        .message.success {

            display: block;

            background: #e8f5e9;

            color: #1f7a45;

            border-left:
                4px solid #1f7a45;
        }


        .message.error {

            display: block;

            background: #fbe9e7;

            color: #c62828;

            border-left:
                4px solid #c62828;
        }


        .message.info {

            display: block;

            background: #e3f2fd;

            color: #0d47a1;

            border-left:
                4px solid #0d47a1;
        }


        .btn-recu {

            display: flex;

            background: #2b2725;

            border: none;

            color: #ffffff;

            font-weight: 600;

            padding: 14px 25px;

            border-radius: 10px;

            width: 100%;

            margin-top: 15px;

            text-decoration: none;

            align-items: center;

            justify-content: center;

            gap: 10px;
        }


        .btn-recu:hover {

            background: #1c1a19;

            color: #ffffff;
        }


        .spinner {

            display: inline-block;

            width: 20px;

            height: 20px;

            border:
                3px solid
                rgba(255,255,255,0.3);

            border-radius: 50%;

            border-top-color: #ffffff;

            animation:
                spin 0.8s
                linear infinite;
        }


        @keyframes spin {

            to {

                transform:
                    rotate(360deg);
            }
        }


        .footer-text {

            color: #b5aaa0;

            font-size: 0.8rem;

            margin-top: 20px;
        }


        @media(max-width:576px) {

            .paiement-container {

                padding: 30px 20px;
            }


            .montant {

                font-size: 2.2rem;
            }
        }

    </style>

</head>


<body>


<div class="paiement-container">


    <!-- =========================================================
         LOGO
    ========================================================== -->

    <div class="logo">

        🍷 CAVE <span>À VINS</span>

    </div>


    <div class="sous-titre">

        Paiement sécurisé en ligne

    </div>


    <hr class="separateur">


    <div class="badge-securise">

        <i class="bi bi-shield-check"></i>

        Paiement sécurisé

    </div>


    <?php if (!$est_paye): ?>


        <!-- =====================================================
             MONTANT
        ====================================================== -->

        <div class="montant-box">


            <div class="montant-label">

                Total à payer

            </div>


            <div class="montant">

                <?php

                echo number_format(
                    $total_general,
                    0,
                    ",",
                    " "
                );

                ?>

                <span class="montant-devise">

                    FCFA

                </span>

            </div>


            <div class="ref-commande">

                Commande #

                <?php

                echo htmlspecialchars(
                    $numero_commande
                );

                ?>

            </div>


            <?php if ($frais_livraison > 0): ?>

                <div class="frais">

                    Sous-total :

                    <?php

                    echo number_format(
                        $sous_total,
                        0,
                        ",",
                        " "
                    );

                    ?>

                    FCFA

                    <br>

                    Livraison :

                    <?php

                    echo number_format(
                        $frais_livraison,
                        0,
                        ",",
                        " "
                    );

                    ?>

                    FCFA

                </div>

            <?php endif; ?>


        </div>


        <!-- =====================================================
             BOUTON PAIEMENT
        ====================================================== -->

        <button
            type="button"
            id="btn-kkiapay"
            class="btn-kkiapay"
        >

            <i
                class="bi bi-credit-card-2-front"
            ></i>

            Payer avec KKiaPay

        </button>


        <div
            id="message"
            class="message"
        ></div>


    <?php else: ?>


        <!-- =====================================================
             PAIEMENT DÉJÀ EFFECTUÉ
        ====================================================== -->

        <div
            class="message success"
            style="display:block;"
        >

            <i
                class="bi bi-check-circle-fill"
            ></i>

            Paiement déjà confirmé.

        </div>


        <div
            class="montant-box"
            style="background:#e8f5e9;"
        >

            <div class="montant-label">

                Montant payé

            </div>


            <div
                class="montant"
                style="color:#1f7a45;"
            >

                <?php

                echo number_format(
                    $paiement["montant"]
                    ?? $total_general,
                    0,
                    ",",
                    " "
                );

                ?>

                <span class="montant-devise">

                    FCFA

                </span>

            </div>


            <div class="ref-commande">

                Commande #

                <?php

                echo htmlspecialchars(
                    $numero_commande
                );

                ?>

            </div>

        </div>


        <a
            href="recu_paiement.php?id_commande=<?php echo $id_commande; ?>"
            class="btn-recu"
        >

            <i class="bi bi-receipt"></i>

            Voir mon reçu

        </a>


    <?php endif; ?>


    <hr class="separateur">


    <div class="footer-text">

        <i class="bi bi-lock-fill"></i>

        Transaction sécurisée par KKiaPay

        <br>

        <?php if (KKIAPAY_SANDBOX): ?>

            🔧 Mode Sandbox - Test

        <?php endif; ?>

    </div>


</div>


<!-- =============================================================
     SDK KKiaPay
============================================================== -->

<script
    src="https://cdn.kkiapay.me/k.js"
></script>


<script>

(function () {


    const button =
        document.getElementById(
            "btn-kkiapay"
        );


    const message =
        document.getElementById(
            "message"
        );


    if (!button) {

        return;
    }


    // =========================================================
    // DONNÉES PHP
    // =========================================================

    const amount =
        <?php

        echo json_encode(
            (float) $total_general
        );

        ?>;


    const orderId =
        <?php

        echo (int) $id_commande;

        ?>;


    const customerName =
        <?php

        echo json_encode(

            $client

                ? trim(

                    ($client["prenom"] ?? "") .
                    " " .
                    ($client["nom"] ?? "")

                )

                : ""

        );

        ?>;


    const customerPhone =
        <?php

        echo json_encode(
            $client["telephone"] ?? ""
        );

        ?>;


    const customerEmail =
        <?php

        echo json_encode(
            $client["email"] ?? ""
        );

        ?>;


    const kkiapayKey =
        <?php

        echo json_encode(
            KKIAPAY_PUBLIC_KEY
        );

        ?>;


    // =========================================================
    // MESSAGE
    // =========================================================

    function showMessage(
        text,
        type
    ) {

        if (!message) {

            return;
        }


        message.style.display =
            "block";


        message.className =
            "message " + type;


        message.innerHTML =
            text;
    }


    // =========================================================
    // NETTOYER TÉLÉPHONE
    // =========================================================

    function nettoyerTelephone(
        phone
    ) {

        if (!phone) {

            return "";
        }


        let numero =
            String(phone).trim();


        numero =
            numero.replace(
                /[\s\-().]/g,
                ""
            );


        if (
            numero.startsWith("+229")
        ) {

            numero =
                numero.substring(4);
        }


        if (
            numero.startsWith("00229")
        ) {

            numero =
                numero.substring(5);
        }


        return numero;
    }


    const phoneForKKiaPay =
        nettoyerTelephone(
            customerPhone
        );


    // =========================================================
    // VÉRIFIER SDK
    // =========================================================

    if (
        typeof openKkiapayWidget !==
        "function"
    ) {

        showMessage(

            "❌ Impossible de charger KKiaPay." +
            "<br>Vérifiez votre connexion Internet.",

            "error"

        );

        return;
    }


    // =========================================================
    // CLIC PAIEMENT
    // =========================================================

    button.addEventListener(
        "click",
        function () {


            if (
                !amount ||
                amount <= 0
            ) {

                showMessage(
                    "❌ Le montant du paiement est invalide.",
                    "error"
                );

                return;
            }


            button.disabled =
                true;


            button.innerHTML =
                '<span class="spinner"></span>' +
                ' Ouverture du paiement...';


            // =================================================
            // DONNÉES TRANSACTION
            // =================================================

            const transactionData =
                JSON.stringify({

                    id_commande:
                        orderId,

                    numero:
                        "<?php echo htmlspecialchars(
                            $numero_commande,
                            ENT_QUOTES
                        ); ?>"

                });


            // =================================================
            // OUVRIR KKiaPay
            // =================================================

            try {

                openKkiapayWidget({

                    amount:
                        amount,

                    key:
                        kkiapayKey,

                    sandbox:
                        true,

                    position:
                        "center",

                    theme:
                        "#6d1626",

                    name:
                        customerName,

                    phone:
                        phoneForKKiaPay,

                    email:
                        customerEmail,

                    data:
                        transactionData

                });


            } catch (error) {


                console.error(
                    "Erreur KKiaPay :",
                    error
                );


                showMessage(

                    "❌ Erreur lors de l'ouverture de KKiaPay.<br>" +

                    (
                        error.message ||
                        "Erreur inconnue"
                    ),

                    "error"

                );


                button.disabled =
                    false;


                button.innerHTML =
                    '<i class="bi bi-credit-card-2-front"></i>' +
                    ' Réessayer';

            }

        }
    );


    // =========================================================
    // PAIEMENT RÉUSSI
    // =========================================================

    if (
        typeof addSuccessListener ===
        "function"
    ) {


        addSuccessListener(
            function (response) {


                console.log(
                    "Paiement KKiaPay réussi :",
                    response
                );


                const transactionId =

                    response &&
                    (
                        response.transactionId ||
                        response.transaction_id ||
                        response.id
                    );


                if (!transactionId) {


                    showMessage(

                        "❌ KKiaPay n'a pas fourni " +
                        "de référence de transaction.",

                        "error"

                    );


                    button.disabled =
                        false;


                    button.innerHTML =
                        '<i class="bi bi-credit-card-2-front"></i>' +
                        ' Réessayer';


                    return;
                }


                // =================================================
                // VÉRIFICATION SERVEUR
                // =================================================

                button.innerHTML =
                    '<span class="spinner"></span>' +
                    ' Vérification du paiement...';


                showMessage(

                    "Paiement reçu.<br>" +
                    "Enregistrement sécurisé en cours...",

                    "info"

                );


                fetch(
                    "verifier_paiement_kkiapay.php",
                    {

                        method:
                            "POST",

                        headers: {

                            "Content-Type":
                                "application/x-www-form-urlencoded;charset=UTF-8"

                        },

                        body:

                            new URLSearchParams({

                                id_commande:
                                    orderId,

                                transaction_id:
                                    transactionId

                            })

                    }
                )


                .then(
                    function (response) {


                        if (!response.ok) {

                            throw new Error(
                                "Erreur HTTP " +
                                response.status
                            );
                        }


                        return response.json();

                    }
                )


                .then(
                    function (result) {


                        console.log(
                            "Résultat serveur :",
                            result
                        );


                        // =========================================
                        // SUCCÈS
                        // =========================================

                        if (
                            result.success
                        ) {


                            showMessage(

                                "✅ Paiement confirmé !<br>" +
                                "Paiement enregistré dans la base de données.<br>" +
                                "Redirection vers votre reçu...",

                                "success"

                            );


                            button.style.display =
                                "none";


                            // =====================================
                            // REDIRECTION AUTOMATIQUE
                            // =====================================

                            setTimeout(
                                function () {


                                    window.location.href =
                                        "recu_paiement.php?id_commande=" +
                                        orderId;


                                },
                                1000
                            );


                        }

                        // =========================================
                        // ERREUR
                        // =========================================

                        else {


                            showMessage(

                                "❌ " +

                                (
                                    result.message ||

                                    "Le paiement n'a pas pu être confirmé."
                                ),

                                "error"

                            );


                            button.disabled =
                                false;


                            button.innerHTML =
                                '<i class="bi bi-credit-card-2-front"></i>' +
                                ' Réessayer';

                        }

                    }
                )


                .catch(
                    function (error) {


                        console.error(
                            "Erreur serveur :",
                            error
                        );


                        showMessage(

                            "❌ Erreur lors de la vérification du paiement." +
                            "<br><small>" +
                            error.message +
                            "</small>",

                            "error"

                        );


                        button.disabled =
                            false;


                        button.innerHTML =
                            '<i class="bi bi-credit-card-2-front"></i>' +
                            ' Réessayer';

                    }
                );

            }
        );


    } else {


        console.error(
            "addSuccessListener indisponible."
        );

    }


    // =========================================================
    // PAIEMENT ÉCHOUÉ
    // =========================================================

    if (
        typeof addFailedListener ===
        "function"
    ) {


        addFailedListener(
            function (error) {


                console.error(
                    "Paiement KKiaPay échoué :",
                    error
                );


                let erreurTexte =
                    "";


                if (
                    typeof error ===
                    "string"
                ) {

                    erreurTexte =
                        error;

                }

                else if (
                    error &&
                    error.message
                ) {

                    erreurTexte =
                        error.message;

                }

                else if (
                    error &&
                    error.error
                ) {

                    erreurTexte =
                        error.error;

                }

                else if (
                    error &&
                    error.reason
                ) {

                    erreurTexte =
                        error.reason;

                }

                else {

                    erreurTexte =
                        "KKiaPay a refusé ou interrompu la transaction.";

                }


                showMessage(

                    "❌ <strong>Paiement échoué !</strong>" +
                    "<br>" +
                    "<small>" +
                    erreurTexte
                        .replace(
                            /</g,
                            "&lt;"
                        )
                        .replace(
                            />/g,
                            "&gt;"
                        ) +
                    "</small>",

                    "error"

                );


                button.disabled =
                    false;


                button.innerHTML =
                    '<i class="bi bi-credit-card-2-front"></i>' +
                    ' Réessayer';

            }
        );


    } else {


        console.error(
            "addFailedListener indisponible."
        );

    }


})();

</script>


<script
    src="../bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>