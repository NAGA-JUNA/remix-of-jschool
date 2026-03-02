<?php
// SMTP Mail Configuration — reads from DB settings table, falls back to defaults
$_smtpHost = 'mail.jschool.jnvweb.in';
$_smtpPort = 465;
$_smtpUser = 'admin@jschool.jnvweb.in';
$_smtpPass = 'YOUR_EMAIL_PASSWORD';
$_smtpFromName = 'J School';
$_smtpEncryption = 'ssl';

try {
    require_once __DIR__ . '/db.php';
    $_db = getDB();
    $_smtpRows = $_db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_encryption')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($_smtpRows['smtp_host']))       $_smtpHost       = $_smtpRows['smtp_host'];
    if (!empty($_smtpRows['smtp_port']))        $_smtpPort       = (int)$_smtpRows['smtp_port'];
    if (!empty($_smtpRows['smtp_user']))        $_smtpUser       = $_smtpRows['smtp_user'];
    if (!empty($_smtpRows['smtp_pass']))        $_smtpPass       = $_smtpRows['smtp_pass'];
    if (!empty($_smtpRows['smtp_from_name']))   $_smtpFromName   = $_smtpRows['smtp_from_name'];
    if (isset($_smtpRows['smtp_encryption']))   $_smtpEncryption = $_smtpRows['smtp_encryption'];
} catch (Exception $e) {
    // DB unavailable — use hardcoded defaults
}

define('SMTP_HOST', $_smtpHost);
define('SMTP_PORT', $_smtpPort);
define('SMTP_USER', $_smtpUser);
define('SMTP_PASS', $_smtpPass);
define('SMTP_FROM_NAME', $_smtpFromName);
define('SMTP_ENCRYPTION', $_smtpEncryption);

// Include PHPMailer classes
require_once __DIR__ . '/../includes/phpmailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Send an HTML email using SMTP authentication.
 */
function sendMail(string $to, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;

    if ($mail->send()) {
        return true;
    } else {
        error_log('Mail send failed to ' . $to . ': ' . $mail->ErrorInfo);
        return false;
    }
}