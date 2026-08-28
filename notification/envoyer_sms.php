<?php

require_once __DIR__ . "/config_notifications.php";


//===============================================================
// Met un numéro béninois au format international.
//
// Depuis le 30/11/2024, le Bénin est passé à un plan à 10 chiffres
// (préfixe "01", "02"... ajouté). Le format international GARDE
// ce zéro initial : +229 0198988316  (et non +229 198988316).
//===============================================================

function formaterNumeroBenin($telephone)
{
    $telephone = preg_replace('/[^0-9+]/', '', trim($telephone ?? ''));

    if ($telephone === '') {
        return null;
    }

    // Déjà au format international (+229...)
    if (strpos($telephone, '+') === 0) {
        return $telephone;
    }

    // Écrit "229XXXXXXXXXX" sans le +
    if (strpos($telephone, '229') === 0 && strlen($telephone) > 10) {
        return '+' . $telephone;
    }

    // Numéro local à 10 chiffres (ex: 0198988316) -> on garde le 0
    return '+229' . $telephone;
}


//===============================================================
// Envoie un SMS via l'API SMS Partner Bénin.
// Retourne true en cas de succès, false sinon (voir logs PHP).
//===============================================================

function envoyerSMS($telephone, $message)
{
    $numero = formaterNumeroBenin($telephone);

    if (!$numero) {
        error_log("SMS non envoyé : numéro de téléphone invalide ou vide.");
        return false;
    }

    $donnees = [
        "apiKey"       => SMS_API_KEY,
        "phoneNumbers" => $numero,
        "sender"       => SMS_SENDER,
        "gamme"        => SMS_GAMME,
        "message"      => $message,
    ];

    $ch = curl_init(SMS_API_URL);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($donnees),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "cache-control: no-cache",
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $reponse     = curl_exec($ch);
    $erreur_curl = curl_error($ch);
    $code_http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($erreur_curl) {
        error_log("Erreur SMS (cURL) vers $numero : " . $erreur_curl);
        return false;
    }

    if ($code_http < 200 || $code_http >= 300) {
        error_log("Erreur SMS (HTTP $code_http) vers $numero : " . $reponse);
        return false;
    }

    return true;
}