<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require 'config.php';

$college_code = $_GET['college_code'] ?? '';

if (!$college_code) {
    echo json_encode(['success' => false, 'error' => 'Missing college code', 'data' => []]);
    exit;
}

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$response = file_get_contents(
    SUPABASE_URL . "/rest/v1/students?college_code=eq.$college_code&select=*",
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$data = json_decode($response, true) ?: [];
echo json_encode(['success' => true, 'data' => $data]);