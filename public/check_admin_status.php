<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require 'config.php';

$input = json_decode(file_get_contents("php://input"), true);
$status_id = trim($input['status_id'] ?? '');

if (empty($status_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Status ID is required'
    ]);
    exit;
}

// Supabase headers
$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

// Fetch record from Supabase
$fetchUrl = SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$status_id&select=*";

$fetchRes = file_get_contents(
    $fetchUrl,
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$records = json_decode($fetchRes, true);

if (!$records || count($records) === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Status ID not found'
    ]);
    exit;
}

$record = $records[0];

// Return data
echo json_encode([
    'success' => true,
    'data' => [
        'status_id' => $record['status_id'],
        'status' => $record['status'],
        'college_name' => $record['college_name'],
        'college_code' => $record['college_code'],
        'email' => $record['email'],
        'address' => $record['address'],
        'phones_allowed' => $record['phones_allowed'],
        'supervisor_present' => $record['supervisor_present'],
        'created_at' => $record['created_at']
    ]
]);