// ========== delete_student.php ==========
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require 'config.php';

$input = json_decode(file_get_contents("php://input"), true);
$id = $input['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false]);
    exit;
}

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

file_get_contents(
    SUPABASE_URL . "/rest/v1/students?id=eq.$id",
    false,
    stream_context_create([
        "http" => [
            "method" => "DELETE",
            "header" => $headers
        ]
    ])
);

echo json_encode(['success' => true]);