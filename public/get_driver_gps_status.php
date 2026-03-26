<?php
require 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}

$driver_id = $_GET['driver_id'] ?? null;

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Missing driver_id"]);
    exit;
}

try {
    $drivers = supabaseRequest(
        "/rest/v1/drivers?id=eq." . intval($driver_id) . "&select=gps_active"
    );

    if (empty($drivers)) {
        echo json_encode(["success" => false, "message" => "Driver not found"]);
        exit;
    }

    // Default to true if field is null (not yet set)
    $gps_active = $drivers[0]['gps_active'] ?? true;

    echo json_encode([
        "success" => true,
        "gps_active" => (bool)$gps_active
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error fetching gps_active"]);
}