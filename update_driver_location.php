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
        
        if ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
}

function distanceInMeters($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = ($lat2 - $lat1) * M_PI / 180;
    $dLng = ($lng2 - $lng1) * M_PI / 180;
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos($lat1 * M_PI / 180) * cos($lat2 * M_PI / 180) *
         sin($dLng / 2) * sin($dLng / 2);
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$data = json_decode(file_get_contents("php://input"), true);
$driver_id = $data['driver_id'] ?? null;
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

if (!$driver_id || !$lat || !$lng) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

// Update driver location
supabaseRequest(
    "/rest/v1/drivers?id=eq.$driver_id",
    "PATCH",
    ["lat" => (float)$lat, "lng" => (float)$lng]
);

// Check proximity and notify students
$notificationsSent = 0;

// Get students assigned to this driver who haven't been notified
$students = supabaseRequest(
    "/rest/v1/students?driver_id=eq.$driver_id&notified=eq.false&select=id,lat,lng,notification_range,expo_push_token"
);

// Get parents
$student_ids = array_column($students, 'id');
$parents = [];
if (!empty($student_ids)) {
    $ids_str = implode(',', $student_ids);
    $parents = supabaseRequest(
        "/rest/v1/parents?student_id=in.($ids_str)&select=student_id,expo_push_token"
    );
}

foreach ($students as $student) {
    $distance = distanceInMeters(
        $lat, 
        $lng,
        $student['lat'],
        $student['lng']
    );

    $range = $student['notification_range'] ?? 300;

    if ($distance <= $range) {
        // Collect tokens
        $tokens = [];
        
        // Student token
        if (!empty($student['expo_push_token'])) {
            $tokens[] = $student['expo_push_token'];
        }

        // Parent tokens
        foreach ($parents as $parent) {
            if ($parent['student_id'] == $student['id'] && !empty($parent['expo_push_token'])) {
                $tokens[] = $parent['expo_push_token'];
            }
        }

        // Send notification
        if (!empty($tokens)) {
            $payload = [];
            foreach ($tokens as $token) {
                $payload[] = [
                    "to" => $token,
                    "title" => "🚌 Bus Arriving!",
                    "body" => "Your bus is within " . round($distance) . "m of your pickup location",
                    "sound" => "default",
                    "priority" => "high",
                    "channelId" => "bus-alerts"
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

            $notificationsSent++;
        }

        // Mark student as notified
        supabaseRequest(
            "/rest/v1/students?id=eq." . $student['id'],
            "PATCH",
            ["notified" => true]
        );
    }
}

echo json_encode([
    "success" => true,
    "notifications_sent" => $notificationsSent
]);