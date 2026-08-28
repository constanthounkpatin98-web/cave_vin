<?php

require_once __DIR__ . "/config_notifications.php";
require_once __DIR__ . "/../PHPMailer/src/Exception.php";
require_once __DIR__ . "/../PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/../PHPMailer/src/SMTP.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


//===============================================================
// Envoie un email HTML via Gmail SMTP.
// Retourne true en cas de succès, false sinon (voir logs PHP).
//===============================================================

function envoyerEmail($destinataire, $nomDestinataire, $sujet, $corpsHtml)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = "UTF-8";

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($destinataire, $nomDestinataire ?: "");

        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $corpsHtml;
        $mail->AltBody = strip_tags($corpsHtml);

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Erreur Email vers $destinataire : " . $mail->ErrorInfo);
        return false;

    }
}
