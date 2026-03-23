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
    // Get supervisor assigned to this driver
    $supervisors = supabaseRequest(
        "/rest/v1/supervisors?driver_id=eq.$driver_id&select=id,lat,lng,name"
    );

    if (empty($supervisors)) {
        echo json_encode(["success" => false, "message" => "No supervisor found"]);
        exit;
    }

    $supervisor = $supervisors[0];

    echo json_encode([
        "success" => true,
        "location" => [
            "lat" => (float)$supervisor['lat'],
            "lng" => (float)$supervisor['lng']
        ],
        "supervisor" => [
            "id" => $supervisor['id'],
            "name" => $supervisor['name']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server error"]);
}