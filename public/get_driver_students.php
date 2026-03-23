<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require 'config.php';

$driver_id = $_GET['driver_id'] ?? '';

if (!$driver_id) {
    echo json_encode(['success' => false, 'message' => 'Missing driver_id']);
    exit;
}

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$response = file_get_contents(
    SUPABASE_URL . "/rest/v1/students?driver_id=eq.$driver_id&select=*&order=id.asc",
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$data = json_decode($response, true) ?: [];

// Return in BOTH old format AND new format for compatibility
echo json_encode([
    'success' => true, 
    'data' => $data,      // OLD format (kept for existing code)
    'students' => $data   // NEW format (for routing)
]);