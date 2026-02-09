<?php
require 'config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$driver_id = $data['driver_id'] ?? null;

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID missing"]);
    exit;
}

supabaseRequest(
    "/rest/v1/drivers?id=eq.$driver_id",
    "PATCH",
    ["ride" => false]
);

echo json_encode(["success" => true]);
