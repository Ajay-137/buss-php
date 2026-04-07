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

// Collect student IDs and tokens separately
$student_ids    = [];
$student_tokens = [];

foreach ($students as $s) {
    $student_ids[] = $s['id'];
    if (!empty($s['expo_push_token'])) {
        $student_tokens[] = $s['expo_push_token'];
    }
}

// Fetch parents of these students
$parent_tokens = [];

if (!empty($student_ids)) {
    $ids_str = implode(',', $student_ids);
    $parents = supabaseRequest(
        "/rest/v1/parents?student_id=in.($ids_str)&select=expo_push_token"
    );

    foreach ($parents as $p) {
        if (!empty($p['expo_push_token'])) {
            $parent_tokens[] = $p['expo_push_token'];
        }
    }
}

// Build payload with separate messages for students and parents
$payload = [];

foreach ($student_tokens as $token) {
    $payload[] = [
        "to"       => $token,
        "title"    => "🚌 Bus Departed",
        "body"     => "Your bus has departed from college. Have a safe journey!",
        "sound"    => "default",
        "priority" => "high"
    ];
}

foreach ($parent_tokens as $token) {
    $payload[] = [
        "to"       => $token,
        "title"    => "🚌 Bus Departed",
        "body"     => "Your child's bus has departed from college.",
        "sound"    => "default",
        "priority" => "high"
    ];
}

// Send notifications with full debug output
$expo_response  = null;
$expo_http_code = null;

if (!empty($payload)) {
    $ch = curl_init("https://exp.host/--/api/v2/push/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Accept: application/json"],
        CURLOPT_POSTFIELDS     => json_encode($payload)
    ]);
    $expo_response  = curl_exec($ch);
    $expo_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

echo json_encode([
    "success"              => true,
    "debug_driver_id"      => $driver_id,
    "debug_students_found" => count($students),
    "debug_student_tokens" => $student_tokens,
    "debug_parent_tokens"  => $parent_tokens,
    "debug_payload_sent"   => $payload,
    "debug_expo_http_code" => $expo_http_code,
    "debug_expo_response"  => json_decode($expo_response, true)
]);