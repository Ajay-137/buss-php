<?php
require 'config.php';
require 'mailer.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$driver_id = $data['driver_id'] ?? null;
$type      = $data['type'] ?? '';

if (!$driver_id || !$type) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

/* Fetch driver */
$drivers = supabaseRequest(
    "/rest/v1/drivers?id=eq.$driver_id&select=id,name,college_code"
);

if (empty($drivers)) {
    echo json_encode(["success" => false, "message" => "Driver not found"]);
    exit;
}

$driver = $drivers[0];

/* Fetch students assigned to this driver — include id for parent lookup */
$students = supabaseRequest(
    "/rest/v1/students?driver_id=eq.$driver_id&select=id,expo_push_token,name"
);

/* Fetch admin for this college */
$admins = supabaseRequest(
    "/rest/v1/admins?college_code=eq." . $driver['college_code'] . "&select=expo_push_token,email"
);

/* Notification content */
$title = "Bus Update";

$student_body = $type === "traffic"
    ? "Your bus is delayed due to traffic. Driver: " . $driver['name']
    : "Bus breakdown or issue reported. Driver: " . $driver['name'];

$parent_body = $type === "traffic"
    ? "Your child's bus is delayed due to traffic. Driver: " . $driver['name']
    : "Your child's bus has reported a breakdown or issue. Driver: " . $driver['name'];

$admin_body = $type === "traffic"
    ? "Bus delayed due to traffic. Driver: " . $driver['name']
    : "Bus breakdown or issue reported. Driver: " . $driver['name'];

/* Collect student tokens */
$student_tokens = [];
$student_ids = [];

foreach ($students as $s) {
    $student_ids[] = $s['id'];
    if (!empty($s['expo_push_token'])) {
        $student_tokens[] = $s['expo_push_token'];
    }
}

/* Fetch parents of these students */
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

/* Collect admin tokens + send email */
$admin_tokens = [];

foreach ($admins as $a) {
    if (!empty($a['expo_push_token'])) {
        $admin_tokens[] = $a['expo_push_token'];
    }

    /* Email admin */
    if (!empty($a['email'])) {
        try {
            $emailBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #F44336; border-bottom: 3px solid #FF5722; padding-bottom: 10px;'>
                        🚨 Bus Alert Notification
                    </h2>

                    <div style='background: #FFF3E0; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #FF9800;'>
                        <h3 style='margin-top: 0; color: #E65100;'>Alert Details</h3>
                        <p><strong>Type:</strong> " . ($type === 'traffic' ? 'Traffic Delay' : 'Breakdown / Trouble') . "</p>
                        <p><strong>Driver:</strong> " . $driver['name'] . "</p>
                        <p><strong>College Code:</strong> " . $driver['college_code'] . "</p>
                        <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                    </div>

                    <div style='background: #E3F2FD; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #1976D2;'>Message</h3>
                        <p style='font-size: 16px; color: #333;'>$admin_body</p>
                    </div>

                    <div style='background: #f9f9f9; padding: 15px; border-radius: 8px;'>
                        <p style='margin: 0; font-size: 13px; color: #666;'>
                            <strong>Action Required:</strong> Please check on the bus status and coordinate with the driver if necessary.
                        </p>
                    </div>

                    <p style='color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;'>
                        This is an automated notification from the Bus Tracking System.
                    </p>
                </div>
            ";

            sendMail($a['email'], "Bus Alert - " . $driver['college_code'], $emailBody);
        } catch (Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
        }
    }
}

/* Build push payload with separate messages per group */
$payload = [];

foreach ($student_tokens as $token) {
    $payload[] = [
        "to"       => $token,
        "title"    => $title,
        "body"     => $student_body,
        "sound"    => "default",
        "priority" => "high"
    ];
}

foreach ($parent_tokens as $token) {
    $payload[] = [
        "to"       => $token,
        "title"    => $title,
        "body"     => $parent_body,
        "sound"    => "default",
        "priority" => "high"
    ];
}

foreach ($admin_tokens as $token) {
    $payload[] = [
        "to"       => $token,
        "title"    => $title,
        "body"     => $admin_body,
        "sound"    => "default",
        "priority" => "high"
    ];
}

/* Send Expo push notifications */
if (!empty($payload)) {
    $ch = curl_init("https://exp.host/--/api/v2/push/send");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Push notification failed. HTTP Code: $httpCode, Response: $response");
    }
}

echo json_encode([
    "success" => true,
    "message" => "Notifications sent successfully",
    "students_notified" => count($students),
    "parents_notified"  => count($parent_tokens),
    "admins_notified"   => count($admins)
]);