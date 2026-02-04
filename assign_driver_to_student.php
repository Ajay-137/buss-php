// ========== assign_driver_to_student.php ==========
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require 'config.php';

$input = json_decode(file_get_contents("php://input"), true);
$student_id = $input['student_id'] ?? '';
$driver_id = $input['driver_id'] ?? '';

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

file_get_contents(
    SUPABASE_URL . "/rest/v1/students?id=eq.$student_id",
    false,
    stream_context_create([
        "http" => [
            "method" => "PATCH",
            "header" => $headers,
            "content" => json_encode(['driver_id' => $driver_id])
        ]
    ])
);

echo json_encode(['success' => true]);
