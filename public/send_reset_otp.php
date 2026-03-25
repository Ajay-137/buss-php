<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
    echo json_encode(['success' => false, 'error' => 'Only Gmail addresses are allowed']);
    exit;
}

try {
    $headers = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json'
    ];

    // Check if admin exists
    $url = SUPABASE_URL . "/rest/v1/admins?email=eq." . urlencode($email) . "&select=email,college_name";
    $context = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers)]]);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $admins = json_decode($response, true);

    if (empty($admins) || !isset($admins[0])) {
        echo json_encode(['success' => false, 'error' => 'No account found with this email']);
        exit;
    }

    // Generate OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete old OTPs
    $deleteUrl = SUPABASE_URL . "/rest/v1/otp_requests?email=eq." . urlencode($email) . "&purpose=eq.reset";
    $deleteContext = stream_context_create(['http' => ['method' => 'DELETE', 'header' => implode("\r\n", $headers)]]);
    file_get_contents($deleteUrl, false, $deleteContext);

    // Insert new OTP
    $otpData = json_encode(['email' => $email, 'otp' => $otp, 'purpose' => 'reset', 'expires_at' => $expiresAt]);
    $insertContext = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $otpData]]);
    $insertResponse = file_get_contents(SUPABASE_URL . "/rest/v1/otp_requests", false, $insertContext);

    if ($insertResponse === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to generate OTP']);
        exit;
    }

    // Send email via Brevo API
    $emailPayload = json_encode([
        "sender" => ["name" => "Bus App", "email" => "ajaymg137@gmail.com"],
        "to" => [["email" => $email]],
        "subject" => "Password Reset OTP - College Bus Tracker",
        "htmlContent" => "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px'>
                <div style='background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div style='background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px'>
                    <p>You requested to reset your password. Use the OTP below:</p>
                    <div style='background:white;border:2px solid #667eea;border-radius:8px;padding:20px;text-align:center;margin:20px 0'>
                        <div style='font-size:32px;font-weight:bold;color:#667eea;letter-spacing:5px'>$otp</div>
                    </div>
                    <p><strong>This OTP expires in 10 minutes.</strong></p>
                    <p>If you did not request this, ignore this email.</p>
                </div>
            </div>
        "
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

    error_log("Brevo reset OTP response: $brevoCode - $brevoResponse");

    if ($brevoCode === 201) {
        echo json_encode(['success' => true, 'message' => 'OTP sent to your email']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send OTP email']);
    }

} catch (Exception $e) {
    error_log("Send Reset OTP Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}