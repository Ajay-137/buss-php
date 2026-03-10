<?php
require 'config.php';

header('Content-Type: application/json');

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
$driver_id = $data['driver_id'] ?? null;

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID missing"]);
    exit;
}

// Update ride status
supabaseRequest(
    "/rest/v1/drivers?id=eq.$driver_id",
    "PATCH",
    ["ride" => true]
);

// Reset notified flags for all students of this driver
supabaseRequest(
    "/rest/v1/students?driver_id=eq.$driver_id",
    "PATCH",
    ["notified" => false]
);

// Fetch students assigned to this driver
$students = supabaseRequest(
    "/rest/v1/students?driver_id=eq.$driver_id&select=id,expo_push_token"
);

// Collect student IDs and push tokens
$student_ids = [];
$tokens = [];

foreach ($students as $s) {
    $student_ids[] = $s['id'];
    if (!empty($s['expo_push_token'])) {
        $tokens[] = $s['expo_push_token'];
    }
}

// Fetch parents of these students
if (!empty($student_ids)) {
    $ids_str = implode(',', $student_ids);
    $parents = supabaseRequest(
        "/rest/v1/parents?student_id=in.($ids_str)&select=expo_push_token"
    );

    foreach ($parents as $p) {
        if (!empty($p['expo_push_token'])) {
            $tokens[] = $p['expo_push_token'];
        }
    }
}

// Send notifications
if (!empty($tokens)) {
    $payload = [];
    foreach ($tokens as $token) {
        $payload[] = [
            "to" => $token,
            "title" => "🚌 Bus Departed",
            "body" => "Your bus has started the route",
            "sound" => "default",
            "priority" => "high"
        ];
    }

    $ch = curl_init("https://exp.host/--/api/v2/push/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    curl_exec($ch);
    curl_close($ch);
}

echo json_encode(["success" => true]);