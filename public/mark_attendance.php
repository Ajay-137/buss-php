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
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Prefer: return=representation'
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return null;
        }
        
        return json_decode($response, true);
    }
}

$data = json_decode(file_get_contents("php://input"), true);
$student_id = $data['student_id'] ?? null;
$supervisor_id = $data['supervisor_id'] ?? null;

if (!$student_id || !$supervisor_id) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    $today = date('Y-m-d');

    // Check if already marked today
    $existing = supabaseRequest(
        "/rest/v1/attendance?student_id=eq.$student_id&date=eq.$today&select=id"
    );

    if ($existing && !empty($existing)) {
        echo json_encode(["success" => false, "message" => "Already marked today"]);
        exit;
    }

    // Get student details
    $students = supabaseRequest(
        "/rest/v1/students?id=eq.$student_id&select=name,expo_push_token"
    );

    if (!$students || empty($students)) {
        echo json_encode(["success" => false, "message" => "Student not found"]);
        exit;
    }

    $student = $students[0];

    // Mark attendance
    $attendanceResult = supabaseRequest(
        "/rest/v1/attendance",
        "POST",
        [
            "student_id" => (int)$student_id,
            "supervisor_id" => (int)$supervisor_id,
            "date" => $today,
            "marked_at" => date('Y-m-d H:i:s'),
            "marked_automatically" => false
        ]
    );

    if (!$attendanceResult) {
        echo json_encode(["success" => false, "message" => "Database error"]);
        exit;
    }

    // Get parent push tokens
    $parents = supabaseRequest(
        "/rest/v1/parents?student_id=eq.$student_id&select=expo_push_token"
    );

    // Send notifications
    $tokens = [];

    if (!empty($student['expo_push_token'])) {
        $tokens[] = [
            "to" => $student['expo_push_token'],
            "title" => "✅ Attendance Marked",
            "body" => "Your attendance has been marked for today's bus ride",
            "sound" => "default",
            "priority" => "high",
            "channelId" => "attendance"
        ];
    }

    if ($parents) {
        foreach ($parents as $parent) {
            if (!empty($parent['expo_push_token'])) {
                $tokens[] = [
                    "to" => $parent['expo_push_token'],
                    "title" => "✅ Attendance Marked",
                    "body" => "{$student['name']}'s attendance has been marked",
                    "sound" => "default",
                    "priority" => "high",
                    "channelId" => "attendance"
                ];
            }
        }
    }

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

    echo json_encode([
        "success" => true,
        "message" => "Attendance marked successfully"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Server error"
    ]);
}