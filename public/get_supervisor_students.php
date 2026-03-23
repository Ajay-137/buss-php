<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return [];
        }
        
        return json_decode($response, true) ?: [];
    }
}

$driver_id = $_GET['driver_id'] ?? null;

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID missing", "students" => []]);
    exit;
}

try {
    // Get all students assigned to this driver
    $students = supabaseRequest(
        "/rest/v1/students?driver_id=eq.$driver_id&select=id,name,reg_number,lat,lng,notification_range"
    );

    if (empty($students)) {
        echo json_encode([
            "success" => true,
            "students" => []
        ]);
        exit;
    }

    // Get today's attendance records
    $student_ids = array_column($students, 'id');
    $today = date('Y-m-d');
    
    $attendance = [];
    if (!empty($student_ids)) {
        $ids_str = implode(',', $student_ids);
        $attendance = supabaseRequest(
            "/rest/v1/attendance?student_id=in.($ids_str)&date=eq.$today&select=student_id"
        );
    }

    // Create attendance map
    $attendanceMap = [];
    foreach ($attendance as $record) {
        $attendanceMap[$record['student_id']] = true;
    }

    // Mark students with boarded_today
    foreach ($students as &$student) {
        $student['boarded_today'] = isset($attendanceMap[$student['id']]);
        $student['notification_range'] = $student['notification_range'] ?? 300;
    }

    echo json_encode([
        "success" => true,
        "students" => $students
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "Error fetching students",
        "students" => []
    ]);
}