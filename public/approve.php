<?php
require 'config.php';
require 'mailer.php';
require 'push_notifications.php'; // ✅ Include push notification utility

ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/admin_signup.log');

// Supabase headers
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
    error_log("APPROVE: Record not found for status_id=$statusId");
    die("Record not found");
}

$record = $records[0];

// Check if already processed
if ($record['status'] !== 'pending') {
    error_log("APPROVE: Already processed (status=" . $record['status'] . ")");
    echo "This request was already processed (Status: " . $record['status'] . ").";
    exit;
}

$collegeName = $record['college_name'];
$email = $record['email'];
$expoPushToken = $record['expo_push_token'] ?? null;

/* ---------- UPDATE STATUS TO APPROVED ---------- */
$updateRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests?status_id=eq.$statusId",
    false,
    stream_context_create([
        "http" => [
            "method" => "PATCH",
            "header" => $headers,
            "content" => json_encode([
                "status" => "approved",
                "updated_at" => date('Y-m-d H:i:s')
            ])
        ]
    ])
);

if ($updateRes === false) {
    error_log("APPROVE: Failed to update status");
    die("Failed to approve");
}

error_log("APPROVE SUCCESS: $statusId");

/* ---------- SEND EMAIL NOTIFICATION ---------- */
try {
    $mail->addAddress($email);
    $mail->Subject = "✅ Registration Approved - $collegeName";

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); padding: 30px; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; text-align: center;'>
                    ✅ Registration Approved!
                </h1>
            </div>

            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='font-size: 16px; color: #333;'>
                    Dear <strong>$collegeName</strong> Administrator,
                </p>

                <div style='background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #4CAF50; margin: 20px 0;'>
                    <p style='font-size: 18px; color: #4CAF50; font-weight: bold; margin: 0;'>
                        🎉 Congratulations!
                    </p>
                    <p style='color: #333; margin-top: 10px;'>
                        Your registration has been approved. You can now access the admin portal.
                    </p>
                </div>

                <div style='background: #E3F2FD; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #1976D2;'>🔐 Login Details</h3>
                    <p style='margin: 5px 0;'><strong>Email:</strong> $email</p>
                    <p style='margin: 5px 0;'><strong>Password:</strong> (The password you created during registration)</p>
                </div>

                <div style='background: #FFF3E0; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #F57C00;'>📱 Next Steps</h3>
                    <ol style='color: #333; line-height: 1.8;'>
                        <li>Open the Bus Tracking Admin app</li>
                        <li>Login with your registered email and password</li>
                        <li>Complete your college profile setup</li>
                        <li>Start managing your bus tracking system</li>
                    </ol>
                </div>

                <div style='text-align: center; margin: 30px 0;'>
                    <p style='color: #666;'>
                        Need help? Contact our support team anytime.
                    </p>
                </div>

                <p style='color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;'>
                    This is an automated email from Bus Tracking System. Please do not reply.
                </p>
            </div>
        </div>
    ";

    $mail->send();
    error_log("✅ Approval email sent to $email");
} catch (Exception $e) {
    error_log("❌ Failed to send approval email: " . $e->getMessage());
}

/* ---------- SEND PUSH NOTIFICATION ---------- */
if ($expoPushToken) {
    error_log("Sending push notification to token: $expoPushToken");
    
    // Use the preset template
    $notification = NotificationTemplates::approvalGranted($collegeName);
    
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
    <title>Approval Successful</title>
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
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #4CAF50;
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
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .close-btn:hover {
            background: #45a049;
        }
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

        <p style="color: #4CAF50;">
            ✉️ Confirmation email sent<br>
            <?php if ($expoPushToken): ?>
                📱 Push notification sent
            <?php else: ?>
                ⚠️ No push token available
            <?php endif; ?>
        </p>

        <button class="close-btn" onclick="window.close()">Close</button>
    </div>
</body>
</html>