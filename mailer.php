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
    $mail->Username   = 'ajaymg137@gmail.com';      // 🔴 CHANGE
    $mail->Password   = 'vhsl zxsa zzvd cpft';         // 🔴 CHANGE
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('YOUR_GMAIL@gmail.com', 'Bus App');
    $mail->isHTML(true);
} catch (Exception $e) {
    // Fail silently (avoid HTML output)
}
