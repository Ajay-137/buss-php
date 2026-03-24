<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // Changed from STARTTLS to SSL
    $mail->Port       = 465;                           // Changed from 587 to 465

    $mail->setFrom(MAIL_USERNAME, 'Bus App');
    $mail->isHTML(true);
} catch (Exception $e) {
    error_log("Mailer setup error: " . $e->getMessage());
}