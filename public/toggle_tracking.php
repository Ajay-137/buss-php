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
$admin_id = $data['admin_id'] ?? null;
$tracking = $data['tracking'] ?? null;

if (!$admin_id || $tracking === null) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    // Update tracking mode
    supabaseRequest(
        "/rest/v1/admins?id=eq.$admin_id",
        "PATCH",
        ["tracking" => (bool)$tracking]
    );

    echo json_encode([
        "success" => true,
        "tracking" => (bool)$tracking
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error updating tracking mode"]);
}