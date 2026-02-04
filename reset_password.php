<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$email = isset($data['email']) ? filter_var($data['email'], FILTER_VALIDATE_EMAIL) : null;
$otp = isset($data['otp']) ? $data['otp'] : null;
$password = isset($data['password']) ? $data['password'] : null;

// Validate input
if (!$email || !$otp || !$password) {
    echo json_encode(['error' => 'Email, OTP, and password are required']);
    exit;
}

// Validate Gmail only
if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
    echo json_encode(['error' => 'Only Gmail addresses are allowed']);
    exit;
}

// Validate OTP format (6 digits)
if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['error' => 'Invalid OTP format']);
    exit;
}

// Validate password strength
if (strlen($password) < 8) {
    echo json_encode(['error' => 'Password must be at least 8 characters long']);
    exit;
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#]).{8,}$/', $password)) {
    echo json_encode(['error' => 'Password must contain uppercase, lowercase, number, and special character']);
    exit;
}

try {
    // Prepare headers for Supabase API
    $headers = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json'
    ];

    // Verify OTP
    $otpUrl = SUPABASE_URL . "/rest/v1/otp_requests?email=eq." . urlencode($email) . "&otp=eq." . urlencode($otp) . "&purpose=eq.reset&select=*";
    
    $otpContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $otpResponse = file_get_contents($otpUrl, false, $otpContext);
    
    if ($otpResponse === false) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    $otpRecords = json_decode($otpResponse, true);

    // Check if OTP exists
    if (empty($otpRecords) || !isset($otpRecords[0])) {
        echo json_encode(['error' => 'Invalid OTP']);
        exit;
    }

    $otpRecord = $otpRecords[0];

    // Check if OTP is expired
    if (strtotime($otpRecord['expires_at']) < time()) {
        echo json_encode(['error' => 'OTP has expired. Please request a new one']);
        exit;
    }

    // Hash the new password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Update password in admin_signup_requests table
    $updateData = json_encode([
        'password_hash' => $passwordHash
    ]);

    $updateUrl = SUPABASE_URL . "/rest/v1/admins?email=eq." . urlencode($email);
    
    $updateContext = stream_context_create([
        'http' => [
            'method' => 'PATCH',
            'header' => implode("\r\n", $headers),
            'content' => $updateData
        ]
    ]);

    $updateResponse = file_get_contents($updateUrl, false, $updateContext);

    if ($updateResponse === false) {
        echo json_encode(['error' => 'Failed to update password']);
        exit;
    }

    // Delete the used OTP
    $deleteUrl = SUPABASE_URL . "/rest/v1/otp_requests?email=eq." . urlencode($email) . "&purpose=eq.reset";
    
    $deleteContext = stream_context_create([
        'http' => [
            'method' => 'DELETE',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    file_get_contents($deleteUrl, false, $deleteContext);

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);

} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while resetting password']);
}