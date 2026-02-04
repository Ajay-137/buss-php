<?php
// ========== add_supervisor.php ==========
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require 'config.php';

$input = json_decode(file_get_contents("php://input"), true);

$college_code = $input['college_code'] ?? '';
$name = trim($input['name'] ?? '');
$password = $input['password'] ?? '';
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;

if (!$college_code || !$name || !$password || $lat === null || $lng === null) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$data = [
    'college_code' => $college_code,
    'name' => $name,
    'password_hash' => $password_hash,
    'lat' => $lat,
    'lng' => $lng
];

file_get_contents(
    SUPABASE_URL . "/rest/v1/supervisors",
    false,
    stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => $headers,
            "content" => json_encode($data)
        ]
    ])
);

echo json_encode(['success' => true]);






















