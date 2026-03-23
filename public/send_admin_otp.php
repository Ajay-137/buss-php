<?php
require 'config.php';
require 'mailer.php';

header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

// Validate Gmail only
if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
    echo json_encode(['success' => false, 'error' => 'Only Gmail allowed']);
    exit;
}

// Generate OTP
$otp = random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 mins

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

// Delete old OTPs for this email
file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_otps?email=eq.$email",
    false,
    stream_context_create([
        "http" => [
            "method" => "DELETE",
            "header" => $headers
        ]
    ])
);

// Insert new OTP
$response = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_otps",
    false,
    stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => $headers,
            "content" => json_encode([
                "email" => $email,
                "otp" => password_hash($otp, PASSWORD_DEFAULT),
                "expires_at" => $expiresAt,
                "verified" => false
            ])
        ]
    ])
);

// Send email
$mail->addAddress($email);
$mail->Subject = "College Registration OTP";
$mail->Body = "
Your OTP for college registration is:

<b>$otp</b>

This OTP is valid for 5 minutes.

If you did not request this, ignore this email.
";

$mail->send();

echo json_encode(['success' => true]);
