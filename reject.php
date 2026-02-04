<?php
require 'config.php';
require 'mailer.php';
require 'push_notifications.php'; // ✅ Include push notification utility

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/admin_signup.log');

// Supabase headers
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
$fetchUrl = SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId&select=*";

$fetchRes = file_get_contents(
    $fetchUrl,
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$records = json_decode($fetchRes, true);

if (!$records || count($records) === 0) {
    error_log("REJECT: Record not found for status_id=$statusId");
    die("Record not found");
}

$record = $records[0];

// Check if already processed
if ($record['status'] !== 'pending') {
    error_log("REJECT: Already processed (status=" . $record['status'] . ")");
    echo "This request was already processed (Status: " . $record['status'] . ").";
    exit;
}

$collegeName = $record['college_name'];
$email = $record['email'];
$expoPushToken = $record['expo_push_token'] ?? null;

/* ---------- UPDATE STATUS TO REJECTED ---------- */
$updateRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId",
    false,
    stream_context_create([
        "http" => [
            "method" => "PATCH",
            "header" => $headers,
            "content" => json_encode([
                "status" => "rejected",
                "updated_at" => date('Y-m-d H:i:s')
            ])
        ]
    ])
);

if ($updateRes === false) {
    error_log("REJECT: Failed to update status");
    die("Failed to reject");
}

error_log("REJECT SUCCESS: $statusId");

/* ---------- SEND EMAIL NOTIFICATION ---------- */
try {
    $mail->addAddress($email);
    $mail->Subject = "Registration Update - $collegeName";

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #f44336 0%, #e53935 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; text-align: center;'>
                    Registration Status Update
                </h1>
            </div>

            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='font-size: 16px; color: #333;'>
                    Dear <strong>$collegeName</strong> Administrator,
                </p>

                <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #f44336; margin: 20px 0;'>
                    <p style='font-size: 18px; color: #f44336; font-weight: bold; margin: 0;'>
                        Registration Not Approved
                    </p>
                    <p style='color: #333; margin-top: 10px;'>
                        We regret to inform you that your registration request could not be approved at this time.
                    </p>
                </div>

                <div style='background: #FFF3E0; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #F57C00;'>📋 Your Status ID</h3>
                    <p style='font-size: 20px; font-weight: bold; color: #1976D2; margin: 10px 0;'>
                        $statusId
                    </p>
                </div>

                <div style='background: #E3F2FD; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #1976D2;'>💬 Next Steps</h3>
                    <p style='color: #333; line-height: 1.8;'>
                        If you believe this decision was made in error or if you have additional information to provide, 
                        please contact our support team with your Status ID.
                    </p>
                    <p style='color: #333;'>
                        <strong>Support Email:</strong> support@bustracking.com<br>
                        <strong>Status ID:</strong> $statusId
                    </p>
                </div>

                <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; color: #666; font-size: 14px;'>
                        <strong>Common reasons for rejection:</strong>
                    </p>
                    <ul style='color: #666; margin: 10px 0;'>
                        <li>Incomplete or incorrect information</li>
                        <li>Invalid college code</li>
                        <li>Duplicate registration</li>
                        <li>Missing required documentation</li>
                    </ul>
                </div>

                <p style='color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;'>
                    This is an automated email from Bus Tracking System. Please do not reply.<br>
                    For assistance, contact our support team.
                </p>
            </div>
        </div>
    ";

    $mail->send();
    error_log("✅ Rejection email sent to $email");
} catch (Exception $e) {
    error_log("❌ Failed to send rejection email: " . $e->getMessage());
}

/* ---------- SEND PUSH NOTIFICATION ---------- */
if ($expoPushToken) {
    error_log("Sending push notification to token: $expoPushToken");
    
    // Use the preset template
    $notification = NotificationTemplates::approvalRejected($collegeName);
    
    $result = sendPushNotification(
        $expoPushToken,
        $notification['title'],
        $notification['body'],
        $notification['data']
    );

    if ($result['success']) {
        error_log("✅ Push notification sent successfully");
    } else {
        error_log("❌ Push notification failed: " . $result['error']);
    }
} else {
    error_log("⚠️ No push token found for this user");
}

/* ---------- SUCCESS RESPONSE ---------- */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rejection Processed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #f44336;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        .details {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
        .close-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .close-btn:hover {
            background: #e53935;
        }
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

        <p style="color: #f44336;">
            ✉️ Notification email sent<br>
            <?php if ($expoPushToken): ?>
                📱 Push notification sent
            <?php else: ?>
                ⚠️ No push token available
            <?php endif; ?>
        </p>

        <p style="font-size: 12px; color: #999; margin-top: 20px;">
            The college admin has been notified about this decision.
        </p>

        <button class="close-btn" onclick="window.close()">Close</button>
    </div>
</body>
</html>