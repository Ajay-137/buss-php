// ========== get_driver_students.php ==========
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require 'config.php';

$driver_id = $_GET['driver_id'] ?? '';

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$response = file_get_contents(
    SUPABASE_URL . "/rest/v1/students?driver_id=eq.$driver_id&select=*",
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$data = json_decode($response, true);
echo json_encode(['success' => true, 'data' => $data]);