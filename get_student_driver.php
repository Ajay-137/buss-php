<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require 'config.php';

$student_id = $_GET['student_id'] ?? '';

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$response = file_get_contents(
    SUPABASE_URL . "/rest/v1/students?id=eq.$student_id&select=driver_id",
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$data = json_decode($response, true);
$driver_id = isset($data[0]) ? $data[0]['driver_id'] : null;
echo json_encode(['driver_id' => $driver_id]);