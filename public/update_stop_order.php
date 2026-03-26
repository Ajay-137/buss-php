<?php
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

        if ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}

$input = json_decode(file_get_contents("php://input"), true);
$students = $input['students'] ?? [];

if (empty($students)) {
    echo json_encode(["success" => false, "message" => "No students provided"]);
    exit;
}

try {
    foreach ($students as $entry) {
        $student_id = intval($entry['student_id'] ?? 0);
        $stop_order = intval($entry['stop_order'] ?? 0);

        if (!$student_id) continue;

        supabaseRequest(
            "/rest/v1/students?id=eq.$student_id",
            "PATCH",
            ["stop_order" => $stop_order]
        );
    }

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error updating stop order"]);
}