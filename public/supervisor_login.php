<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
}

$data = json_decode(file_get_contents("php://input"), true);

$college_code = trim($data['college_code'] ?? '');
$name         = trim($data['name'] ?? '');
$password     = $data['password'] ?? '';
$pushToken    = $data['expo_push_token'] ?? null;

if (!$college_code || !$name || !$password) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    // Check if supervisor_present is true in admins table
    $admins = supabaseRequest(
        "/rest/v1/admins?college_code=eq.$college_code&select=supervisor_present"
    );

    if (empty($admins) || !$admins[0]['supervisor_present']) {
        echo json_encode(["success" => false, "message" => "Supervisor access not enabled for this college"]);
        exit;
    }

    // Fetch supervisor
    $supervisors = supabaseRequest(
        "/rest/v1/supervisors?college_code=eq.$college_code&name=eq.$name&select=*"
    );

    if (empty($supervisors)) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    $supervisor = $supervisors[0];

    // Verify password
    if (!password_verify($password, $supervisor['password_hash'])) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    // Store push token
    if ($pushToken) {
        supabaseRequest(
            "/rest/v1/supervisors?id=eq." . $supervisor['id'],
            "PATCH",
            ["expo_push_token" => $pushToken]
        );
    }

    echo json_encode([
        "success" => true,
        "supervisor" => [
            "id"        => $supervisor['id'],
            "name"      => $supervisor['name'],
            "driver_id" => $supervisor['driver_id'],
            "lat"       => (float)$supervisor['lat'],
            "lng"       => (float)$supervisor['lng']
        ]
    ]);

} catch (Exception $e) {
    error_log("Supervisor login error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Server error. Please try again."
    ]);
}