<?php
error_log("=== verify_admin_otp.php CALLED ===");
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

require 'config.php';

// ✅ FIX 1: Read input ONCE only
$input = json_decode(file_get_contents("php://input"), true);

$email = $input['email'] ?? null;
$otp   = $input['otp'] ?? null;

error_log("OTP INPUT: email=" . $email . " otp=" . $otp);

// ✅ FIX 2: Single validation check
if (!$email || !$otp) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

error_log("About to verify OTP in OTP table");

// Fetch OTP record
$response = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_otps?email=eq.$email&verified=eq.false&select=*",
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$rows = json_decode($response, true);

if (!$rows || count($rows) === 0) {
    error_log("OTP NOT FOUND for email=" . $email);
    echo json_encode(['success' => false, 'error' => 'OTP not found or already verified']);
    exit;
}

$row = $rows[0];

// Check expiry
if (strtotime($row['expires_at']) < time()) {
    error_log("OTP EXPIRED for email=" . $email);
    echo json_encode(['success' => false, 'error' => 'OTP expired']);
    exit;
}

// Verify OTP
if (!password_verify($otp, $row['otp'])) {
    error_log("INVALID OTP for email=" . $email);
    echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
    exit;
}

// ✅ FIX 3: Move error_log OUTSIDE file_get_contents
error_log("OTP VERIFIED SUCCESSFULLY for email=" . $email);

// Mark OTP as verified
file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_otps?id=eq." . $row['id'],
    false,
    stream_context_create([
        "http" => [
            "method" => "PATCH",
            "header" => $headers,
            "content" => json_encode(["verified" => true])
        ]
    ])
);

echo json_encode(['success' => true]);