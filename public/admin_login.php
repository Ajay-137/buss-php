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
$password = $input['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['success' => false, 'error' => 'Email and password are required']);
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

    // Check admins table (only approved admins are here)
    $url = SUPABASE_URL . "/rest/v1/admins?email=eq." . urlencode($email) . "&select=*";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $admins = json_decode($response, true);

    if (empty($admins) || !isset($admins[0])) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    $admin = $admins[0];

    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    // SUCCESS
    echo json_encode([
        'success' => true,
        'email' => $admin['email'],
        'college_name' => $admin['college_name'],
        'college_code' => $admin['college_code'],
        'admin_id' => $admin['id'],
        'supervisor_present' => $admin['supervisor_present'] ?? false
    ]);

} catch (Exception $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred during login']);
}