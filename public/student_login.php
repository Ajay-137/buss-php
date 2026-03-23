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
$reg_number   = trim($data['reg_number'] ?? '');
$dob          = trim($data['dob'] ?? '');
$is_parent    = $data['is_parent'] ?? false;
$pushToken    = $data['expo_push_token'] ?? null;

if (!$college_code || !$reg_number || !$dob) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    /* Fetch student */
    $students = supabaseRequest(
        "/rest/v1/students?college_code=eq.$college_code&reg_number=eq.$reg_number&dob=eq.$dob&select=*"
    );

    if (empty($students)) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    $student = $students[0];

    /* Check supervisor_present for this college */
    $admins = supabaseRequest(
        "/rest/v1/admins?college_code=eq.$college_code&select=supervisor_present"
    );
    
    $supervisor_present = !empty($admins) && ($admins[0]['supervisor_present'] ?? false);

    /* If student (not parent) and has driver, check if ride is active */
    if (!$is_parent && $student['driver_id']) {
        $drivers_check = supabaseRequest(
            "/rest/v1/drivers?id=eq." . $student['driver_id'] . "&select=ride"
        );

        if (!empty($drivers_check) && ($drivers_check[0]['ride'] ?? false)) {
            echo json_encode([
                "success" => false, 
                "message" => "Cannot login while bus is on active ride. Please wait until the ride is completed."
            ]);
            exit;
        }
    }

    /* Store push token */
    if ($pushToken) {
        if ($is_parent) {
            // Store in parents table
            // First check if parent entry exists
            $existingParents = supabaseRequest(
                "/rest/v1/parents?student_id=eq." . $student['id'] . "&select=id"
            );

            if (empty($existingParents)) {
                // Create new parent entry
                supabaseRequest(
                    "/rest/v1/parents",
                    "POST",
                    [
                        "student_id" => $student['id'],
                        "expo_push_token" => $pushToken
                    ]
                );
            } else {
                // Update existing parent entry
                supabaseRequest(
                    "/rest/v1/parents?student_id=eq." . $student['id'],
                    "PATCH",
                    ["expo_push_token" => $pushToken]
                );
            }
        } else {
            // Store in students table
            supabaseRequest(
                "/rest/v1/students?id=eq." . $student['id'],
                "PATCH",
                ["expo_push_token" => $pushToken]
            );
        }
    }

    /* Fetch driver details if assigned */
    $driver = null;
    $supervisor = null;
    $tracking = true; // Default to driver mode
    
    if ($student['driver_id']) {
        $drivers = supabaseRequest(
            "/rest/v1/drivers?id=eq." . $student['driver_id'] . "&select=id,name,lat,lng"
        );
        if (!empty($drivers)) {
            $driver = $drivers[0];
        }
        
        // Get supervisor for this driver
        $supervisors = supabaseRequest(
            "/rest/v1/supervisors?driver_id=eq." . $student['driver_id'] . "&select=id,name,lat,lng"
        );
        if (!empty($supervisors)) {
            $supervisor = $supervisors[0];
        }
        
        // Get tracking mode from admin
        $admins_tracking = supabaseRequest(
            "/rest/v1/admins?college_code=eq.$college_code&select=tracking"
        );
        if (!empty($admins_tracking)) {
            $tracking = $admins_tracking[0]['tracking'] ?? true;
        }
    }

    echo json_encode([
        "success" => true,
        "student" => [
            "id"   => $student['id'],
            "name" => $student['name'],
            "lat"  => (float)$student['lat'],
            "lng"  => (float)$student['lng'],
            "driver_id" => $student['driver_id'],
            "notification_range" => $student['notification_range'] ?? 300
        ],
        "driver" => $driver,
        "supervisor" => $supervisor,
        "supervisor_present" => $supervisor_present,
        "tracking" => $tracking
    ]);

} catch (Exception $e) {
    error_log("Student login error: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Server error. Please try again."
    ]);
}