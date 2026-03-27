<?php
require 'config.php';
require 'push_notifications.php';

ini_set('log_errors', 1);

$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

$statusId = $_GET['status_id'] ?? null;

if (!$statusId) {
    error_log("REJECT: Missing status_id");
    die("Invalid request");
}

error_log("REJECT CLICKED: status_id=$statusId");

/* ---------- FETCH EXISTING RECORD ---------- */
$fetchRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId&select=*",
    false,
    stream_context_create(["http" => ["method" => "GET", "header" => $headers]])
);

$records = json_decode($fetchRes, true);

if (!$records || count($records) === 0) {
    error_log("REJECT: Record not found for status_id=$statusId");
    die("Record not found");
}

$record = $records[0];

if ($record['status'] !== 'pending') {
    echo "This request was already processed (Status: " . $record['status'] . ").";
    exit;
}

$collegeName   = $record['college_name'];
$email         = $record['email'];
$expoPushToken = $record['expo_push_token'] ?? null;

/* ---------- UPDATE STATUS TO REJECTED ---------- */
$updateRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId",
    false,
    stream_context_create([
        "http" => [
            "method"  => "PATCH",
            "header"  => $headers,
            "content" => json_encode(["status" => "rejected", "updated_at" => date('Y-m-d H:i:s')])
        ]
    ])
);

if ($updateRes === false) {
    error_log("REJECT: Failed to update status");
    die("Failed to reject");
}

error_log("REJECT SUCCESS: $statusId");

/* ---------- SEND EMAIL VIA BREVO ---------- */
try {
    $rejectBody = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,#f44336,#e53935);padding:30px;border-radius:10px 10px 0 0;'>
                <h1 style='color:white;margin:0;text-align:center;'>Registration Status Update</h1>
            </div>
            <div style='background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;'>
                <p>Dear <strong>$collegeName</strong> Administrator,</p>
                <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #f44336;margin:20px 0;'>
                    <p style='font-size:18px;color:#f44336;font-weight:bold;margin:0;'>Registration Not Approved</p>
                    <p style='color:#333;margin-top:10px;'>We regret to inform you that your registration could not be approved at this time.</p>
                </div>
                <div style='background:#FFF3E0;padding:20px;border-radius:8px;margin:20px 0;'>
                    <h3 style='margin-top:0;color:#F57C00;'>📋 Your Status ID</h3>
                    <p style='font-size:20px;font-weight:bold;color:#1976D2;'>$statusId</p>
                </div>
                <div style='background:#E3F2FD;padding:20px;border-radius:8px;'>
                    <h3 style='margin-top:0;color:#1976D2;'>💬 Next Steps</h3>
                    <p>Contact support with your Status ID if you believe this is an error.</p>
                    <p><strong>Status ID:</strong> $statusId</p>
                </div>
                <p style='color:#999;font-size:12px;margin-top:30px;border-top:1px solid #ddd;padding-top:15px;'>
                    This is an automated email. Please do not reply.
                </p>
            </div>
        </div>
    ";

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            "sender"      => ["name" => "Bus App", "email" => "ajaymg137@gmail.com"],
            "to"          => [["email" => $email]],
            "subject"     => "Registration Status Update - $collegeName",
            "htmlContent" => $rejectBody
        ]),
        CURLOPT_HTTPHEADER => ["api-key: " . getenv('BREVO_API_KEY'), "Content-Type: application/json"]
    ]);
    curl_exec($ch);
    curl_close($ch);
    error_log("✅ Rejection email sent to $email");
} catch (Exception $e) {
    error_log("❌ Failed to send rejection email: " . $e->getMessage());
}

/* ---------- SEND PUSH NOTIFICATION ---------- */
if ($expoPushToken) {
    $notification = NotificationTemplates::approvalRejected($collegeName);
    $result = sendPushNotification($expoPushToken, $notification['title'], $notification['body'], $notification['data']);
    error_log($result['success'] ? "✅ Push notification sent" : "❌ Push failed: " . $result['error']);
} else {
    error_log("⚠️ No push token found");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rejection Processed</title>
    <style>
        body { font-family:Arial,sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0; background:linear-gradient(135deg,#667eea,#764ba2); }
        .container { background:white; padding:40px; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center; max-width:500px; }
        .icon { font-size:64px; margin-bottom:20px; }
        h1 { color:#f44336; }
        .details { background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0; text-align:left; }
        .close-btn { background:#f44336; color:white; border:none; padding:12px 30px; border-radius:5px; cursor:pointer; font-size:16px; margin-top:20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">❌</div>
        <h1>Registration Rejected</h1>
        <p><strong><?php echo htmlspecialchars($collegeName); ?></strong> registration has been rejected.</p>
        <div class="details">
            <p><strong>Status ID:</strong> <?php echo htmlspecialchars($statusId); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
            <p><strong>Status:</strong> Rejected</p>
        </div>
        <p style="color:#f44336;">
            ✉️ Notification email sent<br>
            <?php if ($expoPushToken): ?>📱 Push notification sent<?php else: ?>⚠️ No push token<?php endif; ?>
        </p>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>
</body>
</html>