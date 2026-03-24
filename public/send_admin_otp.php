<?php
// Buffer output to prevent any accidental HTML output
ob_start();

require 'config.php';
require 'mailer.php';

// Clear any accidental output
ob_clean();

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

error_log("=== SEND OTP CALLED === email: $email");

if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
    echo json_encode(['success' => false, 'error' => 'Only Gmail allowed']);
    exit;
}

$otp = random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 300);

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

// Delete old OTPs
file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_otps?email=eq.$email",
    false,
    stream_context_create(["http" => ["method" => "DELETE", "header" => $headers]])
);

error_log("Old OTPs deleted");

// Insert new OTP
$insertResponse = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_otps",
    false,
    stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => array_merge($headers, ["Prefer: return=representation"]),
            "content" => json_encode([
                "email" => $email,
                "otp" => password_hash($otp, PASSWORD_DEFAULT),
                "expires_at" => $expiresAt,
                "verified" => false
            ])
        ]
    ])
);

error_log("OTP insert response: " . $insertResponse);

// Send email
// Send email via Brevo API (no SMTP needed)
$emailPayload = json_encode([
    "sender" => ["name" => "Bus App", "email" => "ajaymg137@gmail.com"],
    "to" => [["email" => $email]],
    "subject" => "College Registration OTP",
    "htmlContent" => "Your OTP is: <b>$otp</b>. Valid for 5 minutes."
]);

$ch = curl_init("https://api.brevo.com/v3/smtp/email");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $emailPayload,
    CURLOPT_HTTPHEADER => [
        "api-key: " . getenv('BREVO_API_KEY'),
        "Content-Type: application/json"
    ]
]);

$brevoResponse = curl_exec($ch);
$brevoCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

error_log("Brevo response: $brevoCode - $brevoResponse");

if ($brevoCode === 201) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Email failed']);
}