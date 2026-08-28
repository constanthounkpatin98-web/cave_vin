<?php

session_start();

header(
    "Content-Type: application/json; charset=utf-8"
);

require_once("../connexion.php");


// ================================================================
// CONFIGURATION KKiaPay SERVEUR
// ================================================================
//
// IMPORTANT :
// Remplace les trois valeurs ci-dessous par tes vraies clés
// KKiaPay Sandbox.
//
// Ne mets JAMAIS la clé privée ou secrète dans JavaScript.
// ================================================================

const KKIAPAY_PUBLIC_KEY =
    "567eb6b09c8e11f184146d9578b06a24";

const KKIAPAY_PRIVATE_KEY =
    "tpk_567eddc19c8e11f184146d9578b06a24";

const KKIAPAY_SECRET_KEY =
    "tsk_567eddc29c8e11f184146d9578b06a24";

const KKIAPAY_SANDBOX = true;


// ================================================================
// FONCTION RÉPONSE JSON
// ================================================================

function jsonResponse(
    bool $success,
    string $message,
    array $extra = []
): never {

    echo json_encode(

        array_merge(

            [
                "success" => $success,
                "message" => $message
            ],

            $extra

        ),

        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ================================================================
// VÉRIFIER LA MÉTHODE
// ================================================================

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    jsonResponse(
        false,
        "Méthode non autorisée."
    );
}


// ================================================================
// RÉCUPÉRER LES DONNÉES
// ================================================================

$id_commande =
    filter_input(
        INPUT_POST,
        "id_commande",
        FILTER_VALIDATE_INT
    );


$transaction_id =
    trim(
        $_POST["transaction_id"] ?? ""
    );


if (
    !$id_commande ||
    $transaction_id === ""
) {

    jsonResponse(
        false,
        "Données de paiement incomplètes."
    );
}


// ================================================================
// VÉRIFIER QUE LA COMMANDE EXISTE
// ================================================================

if (
    isset($_SESSION["client_id"])
) {

    $stmt =
        $connexion->prepare("
            SELECT *
            FROM commande
            WHERE id_commande = ?
            AND id_client = ?
            LIMIT 1
        ");

    $stmt->execute([

        $id_commande,

        $_SESSION["client_id"]

    ]);

}

else {

    $stmt =
        $connexion->prepare("
            SELECT *
            FROM commande
            WHERE id_commande = ?
            LIMIT 1
        ");

    $stmt->execute([

        $id_commande

    ]);
}


$commande =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$commande) {

    jsonResponse(
        false,
        "Commande introuvable."
    );
}


// ================================================================
// CALCULER SOUS-TOTAL
// ================================================================

$stmt =
    $connexion->prepare("
        SELECT
            COALESCE(
                SUM(sous_total),
                0
            )
        FROM ligne_commande
        WHERE id_commande = ?
    ");

$stmt->execute([
    $id_commande
]);


$sous_total =
    (float) $stmt->fetchColumn();


// ================================================================
// FRAIS DE LIVRAISON
// ================================================================

$stmt =
    $connexion->prepare("
        SELECT
            COALESCE(
                frais_livraison,
                0
            )
        FROM livraison
        WHERE id_commande = ?
        LIMIT 1
    ");

$stmt->execute([
    $id_commande
]);


$frais_livraison =
    (float) (
        $stmt->fetchColumn() ?? 0
    );


// ================================================================
// MONTANT ATTENDU
// ================================================================

$montant_attendu =
    $sous_total +
    $frais_livraison;


if (
    $montant_attendu <= 0
) {

    jsonResponse(
        false,
        "Montant de commande invalide."
    );
}


// ================================================================
// CHARGER LE SDK KKiaPay
// ================================================================

$autoloads = [

    __DIR__ .
    "/../vendor/autoload.php",

    __DIR__ .
    "/../../vendor/autoload.php",

    __DIR__ .
    "/vendor/autoload.php"

];


foreach (
    $autoloads as $autoload
) {

    if (
        is_file($autoload)
    ) {

        require_once $autoload;

        break;
    }
}


// ================================================================
// VÉRIFIER LE SDK
// ================================================================

if (
    !class_exists(
        "\\Kkiapay\\Kkiapay"
    )
) {

    // ============================================================
    // DEBUG TEMPORAIRE
    // À retirer une fois le problème résolu.
    // ============================================================

    $debug_dir = __DIR__;

    $debug_chemins = [];

    foreach ($autoloads as $chemin) {

        $debug_chemins[] = [
            "chemin" => $chemin,
            "existe" => is_file($chemin)
        ];
    }

    jsonResponse(

        false,

        "SDK PHP KKiaPay absent. " .
        "Exécutez : composer require kkiapay/kkiapay-php",

        [
            "debug_dir" => $debug_dir,
            "debug_chemins" => $debug_chemins
        ]

    );
}


// ================================================================
// VÉRIFIER LES CLÉS
// ================================================================

if (

    KKIAPAY_PUBLIC_KEY ===
    "VOTRE_CLE_PUBLIQUE_SANDBOX"

    ||

    KKIAPAY_PRIVATE_KEY ===
    "VOTRE_CLE_PRIVEE_SANDBOX"

    ||

    KKIAPAY_SECRET_KEY ===
    "VOTRE_CLE_SECRETE_SANDBOX"

) {

    jsonResponse(

        false,

        "Les clés serveur KKiaPay Sandbox " .
        "ne sont pas configurées."

    );
}


// ================================================================
// VÉRIFICATION KKiaPay
// ================================================================

try {


    $kkiapay =
        new \Kkiapay\Kkiapay(

            KKIAPAY_PUBLIC_KEY,

            KKIAPAY_PRIVATE_KEY,

            KKIAPAY_SECRET_KEY,

            KKIAPAY_SANDBOX

        );


    // ============================================================
    // VÉRIFIER LA TRANSACTION
    // ============================================================

    $transaction =
        $kkiapay->verifyTransaction(
            $transaction_id
        );


    $data =

        is_object($transaction)

            ? get_object_vars(
                $transaction
            )

            : (

                is_array($transaction)

                    ? $transaction

                    : []

            );


    // ============================================================
    // RÉCUPÉRER STATUT
    // ============================================================

    $status =
        strtoupper(

            (string) (

                $data["status"]

                ??

                $data["statut"]

                ??

                ""

            )

        );


    // ============================================================
    // RÉCUPÉRER MONTANT
    // ============================================================

    $amount =
        (float) (

            $data["amount"]

            ??

            0

        );


    // ============================================================
    // RÉCUPÉRER RÉFÉRENCE
    // ============================================================

    $verifiedId =
        (string) (

            $data["transactionId"]

            ??

            $data["transaction_id"]

            ??

            $transaction_id

        );


    // ============================================================
    // TRANSACTION RÉUSSIE ?
    // ============================================================

    if (
        $status !== "SUCCESS"
    ) {

        jsonResponse(

            false,

            "KKiaPay n'a pas confirmé cette transaction.",

            [
                "kkiapay_status" =>
                    $status
            ]

        );
    }


    // ============================================================
    // VÉRIFICATION DU MONTANT
    // ============================================================

    if (

        abs(
            $amount -
            $montant_attendu
        ) > 0.01

    ) {

        jsonResponse(

            false,

            "Le montant de la transaction " .
            "ne correspond pas au total de la commande.",

            [

                "montant_paye" =>
                    $amount,

                "montant_attendu" =>
                    $montant_attendu

            ]

        );
    }


    // ============================================================
    // DÉBUT TRANSACTION MYSQL
    // ============================================================

    $connexion->beginTransaction();


    // ============================================================
    // CHERCHER PAIEMENT EXISTANT
    // ============================================================

    $stmt =
        $connexion->prepare("
            SELECT *
            FROM paiement
            WHERE id_commande = ?
            LIMIT 1
            FOR UPDATE
        ");

    $stmt->execute([
        $id_commande
    ]);


    $paiement =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    // ============================================================
    // SI PAIEMENT EXISTE
    // → UPDATE
    // ============================================================

    if ($paiement) {


        $stmt =
            $connexion->prepare("
                UPDATE paiement
                SET
                    date_paiement = ?,
                    mode_paiement = ?,
                    montant = ?,
                    statut = ?,
                    reference_transaction = ?
                WHERE id_paiement = ?
            ");


        $stmt->execute([

            date(
                "Y-m-d H:i:s"
            ),

            "KKiaPay",

            $amount,

            "Validé",

            $verifiedId,

            $paiement[
                "id_paiement"
            ]

        ]);

    }


    // ============================================================
    // SINON
    // → INSERT AUTOMATIQUE
    // ============================================================

    else {


        $stmt =
            $connexion->prepare("
                INSERT INTO paiement
                (
                    date_paiement,
                    mode_paiement,
                    montant,
                    statut,
                    reference_transaction,
                    id_commande
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


        $stmt->execute([

            date(
                "Y-m-d H:i:s"
            ),

            "KKiaPay",

            $amount,

            "Validé",

            $verifiedId,

            $id_commande

        ]);

    }


    // ============================================================
    // METTRE LA COMMANDE À JOUR
    // ============================================================

    $orderCols =
        $connexion
            ->query(
                "SHOW COLUMNS FROM commande"
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            );


    if (
        in_array(
            "statut",
            $orderCols,
            true
        )
    ) {


        $stmt =
            $connexion->prepare("
                UPDATE commande
                SET statut = ?
                WHERE id_commande = ?
            ");


        $stmt->execute([

            "Validée",

            $id_commande

        ]);

    }


    // ============================================================
    // VALIDER MYSQL
    // ============================================================

    $connexion->commit();


    // ============================================================
    // RÉPONSE AU JAVASCRIPT
    // ============================================================

    jsonResponse(

        true,

        "Paiement confirmé et enregistré avec succès.",

        [

            "transaction_id" =>
                $verifiedId,

            "amount" =>
                $amount,

            "id_commande" =>
                $id_commande

        ]

    );


}


// ================================================================
// ERREUR
// ================================================================

catch (
    Throwable $e
) {


    if (
        $connexion->inTransaction()
    ) {

        $connexion->rollBack();
    }


    error_log(
        "Erreur KKiaPay : " .
        $e->getMessage()
    );


    jsonResponse(

        false,

        "Impossible d'enregistrer le paiement " .
        "pour le moment."

    );
}