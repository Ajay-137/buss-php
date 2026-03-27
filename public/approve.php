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
    error_log("APPROVE: Missing status_id");
    die("Invalid request");
}

error_log("APPROVE CLICKED: status_id=$statusId");

/* ---------- FETCH EXISTING RECORD ---------- */
$fetchRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId&select=*",
    false,
    stream_context_create(["http" => ["method" => "GET", "header" => $headers]])
);

$records = json_decode($fetchRes, true);

if (!$records || count($records) === 0) {
    error_log("APPROVE: Record not found for status_id=$statusId");
    die("Record not found");
}

$record = $records[0];

if ($record['status'] !== 'pending') {
    echo "This request was already processed (Status: " . $record['status'] . ").";
    exit;
}

$collegeName     = $record['college_name'];
$email           = $record['email'];
$expoPushToken   = $record['expo_push_token'] ?? null;

/* ---------- UPDATE STATUS TO APPROVED ---------- */
$updateRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId",
    false,
    stream_context_create([
        "http" => [
            "method"  => "PATCH",
            "header"  => $headers,
            "content" => json_encode(["status" => "approved", "updated_at" => date('Y-m-d H:i:s')])
        ]
    ])
);

if ($updateRes === false) {
    error_log("APPROVE: Failed to update status");
    die("Failed to approve");
}

error_log("APPROVE SUCCESS: $statusId");

/* ---------- SEND EMAIL VIA BREVO ---------- */
try {
    $approveBody = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,#4CAF50,#45a049);padding:30px;border-radius:10px 10px 0 0;'>
                <h1 style='color:white;margin:0;text-align:center;'>✅ Registration Approved!</h1>
            </div>
            <div style='background:#f9f9f9;padding:30px;border-radius:0 0 10px 10px;'>
                <p>Dear <strong>$collegeName</strong> Administrator,</p>
                <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #4CAF50;margin:20px 0;'>
                    <p style='font-size:18px;color:#4CAF50;font-weight:bold;margin:0;'>🎉 Congratulations!</p>
                    <p style='color:#333;margin-top:10px;'>Your registration has been approved. You can now access the admin portal.</p>
                </div>
                <div style='background:#E3F2FD;padding:20px;border-radius:8px;margin:20px 0;'>
                    <h3 style='margin-top:0;color:#1976D2;'>🔐 Login Details</h3>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Password:</strong> The password you created during registration</p>
                </div>
                <div style='background:#FFF3E0;padding:20px;border-radius:8px;'>
                    <h3 style='margin-top:0;color:#F57C00;'>📱 Next Steps</h3>
                    <ol style='line-height:1.8;'>
                        <li>Open the Bus Tracking Admin app</li>
                        <li>Login with your registered email and password</li>
                        <li>Start managing your bus tracking system</li>
                    </ol>
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
            "subject"     => "✅ Registration Approved - $collegeName",
            "htmlContent" => $approveBody
        ]),
        CURLOPT_HTTPHEADER => ["api-key: " . getenv('BREVO_API_KEY'), "Content-Type: application/json"]
    ]);
    curl_exec($ch);
    curl_close($ch);
    error_log("✅ Approval email sent to $email");
} catch (Exception $e) {
    error_log("❌ Failed to send approval email: " . $e->getMessage());
}

/* ---------- SEND PUSH NOTIFICATION ---------- */
if ($expoPushToken) {
    $notification = NotificationTemplates::approvalGranted($collegeName);
    $result = sendPushNotification($expoPushToken, $notification['title'], $notification['body'], $notification['data']);
    error_log($result['success'] ? "✅ Push notification sent" : "❌ Push failed: " . $result['error']);
} else {
    error_log("⚠️ No push token found");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Approval Successful</title>
    <style>
        body { font-family:Arial,sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0; background:linear-gradient(135deg,#667eea,#764ba2); }
        .container { background:white; padding:40px; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center; max-width:500px; }
        .success-icon { font-size:64px; margin-bottom:20px; }
        h1 { color:#4CAF50; }
        .details { background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0; text-align:left; }
        .close-btn { background:#4CAF50; color:white; border:none; padding:12px 30px; border-radius:5px; cursor:pointer; font-size:16px; margin-top:20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>College Approved Successfully!</h1>
        <p><strong><?php echo htmlspecialchars($collegeName); ?></strong> has been approved.</p>
        <div class="details">
            <p><strong>Status ID:</strong> <?php echo htmlspecialchars($statusId); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
            <p><strong>Status:</strong> Approved</p>
        </div>
        <p style="color:#4CAF50;">
            ✉️ Confirmation email sent<br>
            <?php if ($expoPushToken): ?>📱 Push notification sent<?php else: ?>⚠️ No push token<?php endif; ?>
        </p>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>
</body>
</html>