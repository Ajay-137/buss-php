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
$supervisor_id = $data['supervisor_id'] ?? null;
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

if (!$supervisor_id || !$lat || !$lng) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

// Update supervisor location
supabaseRequest(
    "/rest/v1/supervisors?id=eq.$supervisor_id",
    "PATCH",
    ["lat" => (float)$lat, "lng" => (float)$lng]
);

// Get supervisor's college_code and driver_id to check if tracking=false
$supervisors_info = supabaseRequest(
    "/rest/v1/supervisors?id=eq.$supervisor_id&select=college_code,driver_id"
);

if (!empty($supervisors_info)) {
    $college_code = $supervisors_info[0]['college_code'];
    $driver_id = $supervisors_info[0]['driver_id'];
    
    // Check if admin has tracking=false (supervisor mode)
    $admins = supabaseRequest(
        "/rest/v1/admins?college_code=eq.$college_code&select=tracking,supervisor_present"
    );
    
    $tracking = !empty($admins) ? ($admins[0]['tracking'] ?? true) : true;
    $supervisor_present = !empty($admins) && ($admins[0]['supervisor_present'] ?? false);
    
    // If tracking=false (supervisor mode) AND supervisor_present=false, do auto-boarding
    if (!$tracking && !$supervisor_present && $driver_id) {
        // Get students assigned to this driver who haven't been notified
        $students = supabaseRequest(
            "/rest/v1/students?driver_id=eq.$driver_id&notified=eq.false&select=id,name,lat,lng,expo_push_token"
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
            
            // Check if student within 50m of supervisor
            if ($distance <= 50) {
                // Mark attendance automatically
                $today = date('Y-m-d');
                
                supabaseRequest(
                    "/rest/v1/attendance",
                    "POST",
                    [
                        "student_id" => (int)$student['id'],
                        "supervisor_id" => (int)$supervisor_id,
                        "date" => $today,
                        "marked_at" => date('Y-m-d H:i:s'),
                        "marked_automatically" => true
                    ]
                );
                
                // Collect tokens
                $tokens = [];
                
                // Student notification
                if (!empty($student['expo_push_token'])) {
                    $tokens[] = [
                        "to" => $student['expo_push_token'],
                        "title" => "🚌 You've boarded the bus",
                        "body" => "You have successfully boarded the bus",
                        "sound" => "default",
                        "priority" => "high",
                        "channelId" => "bus-alerts"
                    ];
                }
                
                // Parent notifications
                foreach ($parents as $parent) {
                    if ($parent['student_id'] == $student['id'] && !empty($parent['expo_push_token'])) {
                        $tokens[] = [
                            "to" => $parent['expo_push_token'],
                            "title" => "🚌 Boarded",
                            "body" => "{$student['name']} boarded the bus",
                            "sound" => "default",
                            "priority" => "high",
                            "channelId" => "bus-alerts"
                        ];
                    }
                }
                
                // Send notifications
                if (!empty($tokens)) {
                    $ch = curl_init("https://exp.host/--/api/v2/push/send");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                        CURLOPT_POSTFIELDS => json_encode($tokens)
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
                
                // Mark student as notified
                supabaseRequest(
                    "/rest/v1/students?id=eq." . $student['id'],
                    "PATCH",
                    ["notified" => true]
                );
            }
        }
    }
}

echo json_encode(["success" => true]);