// ========== add_student.php ==========
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require 'config.php';

$input = json_decode(file_get_contents("php://input"), true);

$college_code = $input['college_code'] ?? '';
$reg_number = trim($input['reg_number'] ?? '');
$name = trim($input['name'] ?? '');
$dob = $input['dob'] ?? '';
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;

if (!$college_code || !$reg_number || !$name || !$dob || $lat === null || $lng === null) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$data = [
    'college_code' => $college_code,
    'reg_number' => $reg_number,
    'name' => $name,
    'dob' => $dob,
    'lat' => $lat,
    'lng' => $lng
];

file_get_contents(
    SUPABASE_URL . "/rest/v1/students",
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