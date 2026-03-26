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
    echo json_encode(["success" => false, "message" => "Driver ID missing"]);
    exit;
}

try {
    $drivers = supabaseRequest(
        "/rest/v1/drivers?id=eq.$driver_id&select=lat,lng,gps_active"
    );

    if (empty($drivers)) {
        echo json_encode(["success" => false, "message" => "Driver not found"]);
        exit;
    }

    $driver = $drivers[0];

    // gps_active defaults to true if not set
    $gps_active = $driver['gps_active'] ?? true;

    if ($gps_active) {
        // Use driver GPS
        echo json_encode([
            "success" => true,
            "location" => [
                "lat" => $driver['lat'],
                "lng" => $driver['lng']
            ]
        ]);
    } else {
        // Use supervisor GPS for this driver
        $supervisors = supabaseRequest(
            "/rest/v1/supervisors?driver_id=eq.$driver_id&select=lat,lng"
        );

        if (empty($supervisors)) {
            // Fallback to driver GPS if no supervisor found
            echo json_encode([
                "success" => true,
                "location" => [
                    "lat" => $driver['lat'],
                    "lng" => $driver['lng']
                ]
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "location" => [
                    "lat" => $supervisors[0]['lat'],
                    "lng" => $supervisors[0]['lng']
                ]
            ]);
        }
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error fetching location"]);
}