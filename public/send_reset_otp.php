<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once 'config.php';
require_once 'mailer.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Valid email is required']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
    echo json_encode(['success' => false, 'error' => 'Only Gmail addresses are allowed']);
    exit;
}

try {
    $headers = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json'
    ];

    // Check if admin exists in admins table
    $url = SUPABASE_URL . "/rest/v1/admins?email=eq." . urlencode($email) . "&select=email,college_name";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $admins = json_decode($response, true);

    if (empty($admins) || !isset($admins[0])) {
        echo json_encode(['success' => false, 'error' => 'No account found with this email']);
        exit;
    }

    $admin = $admins[0];

    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete old OTPs for this email and purpose
    $deleteUrl = SUPABASE_URL . "/rest/v1/otp_requests?email=eq." . urlencode($email) . "&purpose=eq.reset";
    
    $deleteContext = stream_context_create([
        'http' => [
            'method' => 'DELETE',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    file_get_contents($deleteUrl, false, $deleteContext);

    // Store OTP in database
    $otpData = json_encode([
        'email' => $email,
        'otp' => $otp,
        'purpose' => 'reset',
        'expires_at' => $expiresAt
    ]);

    $insertUrl = SUPABASE_URL . "/rest/v1/otp_requests";
    
    $insertContext = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $otpData
        ]
    ]);

    $insertResponse = file_get_contents($insertUrl, false, $insertContext);

    if ($insertResponse === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to generate OTP']);
        exit;
    }

    // Send OTP via email
    try {
        $mail->addAddress($email);
        $mail->Subject = 'Password Reset OTP - College Bus Tracker';
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .otp-box { background: white; border: 2px solid #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
                    .otp { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔐 Password Reset Request</h1>
                    </div>
                    <div class='content'>
                        <p>Hello,</p>
                        <p>You have requested to reset your password for your College Bus Tracker Admin account.</p>
                        <p>Please use the following OTP to reset your password:</p>
                        <div class='otp-box'>
                            <div class='otp'>$otp</div>
                        </div>
                        <p><strong>This OTP will expire in 10 minutes.</strong></p>
                        <p>If you did not request this password reset, please ignore this email and ensure your account is secure.</p>
                        <div class='footer'>
                            <p>College Bus Tracker Admin Portal</p>
                            <p>This is an automated email. Please do not reply.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        error_log("✅ Reset OTP sent to $email");
    } catch (Exception $e) {
        error_log("❌ Failed to send OTP email: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to send OTP email']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'OTP sent to your email'
    ]);

} catch (Exception $e) {
    error_log("Send Reset OTP Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while sending OTP']);
}