<?php
require 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if (!function_exists('supabaseRequest')) {
    function supabaseRequest($endpoint, $method = 'GET', $data = null) {
        $url = SUPABASE_URL . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}

$data = json_decode(file_get_contents("php://input"), true);
$driver_id = $data['driver_id'] ?? null;
$gps_active = $data['gps_active'] ?? null;

if (!$driver_id || $gps_active === null) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    supabaseRequest(
        "/rest/v1/drivers?id=eq." . intval($driver_id),
        "PATCH",
        ["gps_active" => (bool)$gps_active]
    );

    echo json_encode([
        "success" => true,
        "gps_active" => (bool)$gps_active
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error updating gps_active"]);
}