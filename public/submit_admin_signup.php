<?php

error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require 'config.php';
require 'mailer.php';

/* ---------- READ INPUT ---------- */
$input = json_decode(file_get_contents("php://input"), true);

$college_name = trim($input['college_name'] ?? '');
$college_code = trim($input['college_code'] ?? '');
$email        = trim($input['email'] ?? '');
$password     = $input['password'] ?? '';
$lat           = $input['lat'] ?? null;
$lng           = $input['lng'] ?? null;
$address       = trim($input['address'] ?? '');
$phones_allowed      = $input['phones_allowed'] ?? null;
$supervisor_present  = $input['supervisor_present'] ?? null;
$expo_push_token     = trim($input['expo_push_token'] ?? ''); // ✅ NEW: Push token

if (
    !$college_name || !$college_code || !$email || !$password ||
    $lat === null || $lng === null ||
    $phones_allowed === null || $supervisor_present === null
) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

// Log push token for debugging
error_log("Received push token: " . ($expo_push_token ?: "NONE"));

/* ---------- SUPABASE HEADERS ---------- */
$headers = [
    "Content-Type: application/json",
    "apikey: " . SUPABASE_SERVICE_KEY,
    "Authorization: Bearer " . SUPABASE_SERVICE_KEY
];

/* ---------- CHECK FOR EXISTING SUBMISSION ---------- */
// Block if email already exists with status: pending, rejected, OR approved
$checkUrl = SUPABASE_URL . "/rest/v1/admin_signup_requests?email=eq.$email&select=status,status_id";

$checkRes = file_get_contents(
    $checkUrl,
    false,
    stream_context_create([
        "http" => [
            "method" => "GET",
            "header" => $headers
        ]
    ])
);

$existing = json_decode($checkRes, true);

if ($existing && count($existing) > 0) {
    $existingRecord = $existing[0];
    $existingStatus = $existingRecord['status'];
    $existingStatusId = $existingRecord['status_id'];
    
    // Create user-friendly status message
    $statusMessage = [
        'pending' => 'Your registration is already submitted and awaiting approval',
        'rejected' => 'Your previous registration was rejected. Please contact support',
        'approved' => 'Your registration is already approved. Please login'
    ];
    
    echo json_encode([
        'success' => false,
        'already_submitted' => true,
        'status' => $existingStatus,
        'status_id' => $existingStatusId,
        'message' => $statusMessage[$existingStatus] ?? 'Registration already exists'
    ]);
    exit;
}

/* ---------- GENERATE STATUS ID ---------- */
function generateStatusId() {
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    return substr(str_shuffle($chars), 0, 7);
}

$status_id = generateStatusId();

/* ---------- HASH PASSWORD ---------- */
$password_hash = password_hash($password, PASSWORD_DEFAULT);

/* ---------- INSERT INTO admin_signup_requests TABLE ---------- */
$insertData = [
    "college_name"        => $college_name,
    "college_code"        => $college_code,
    "email"               => $email,
    "password_hash"       => $password_hash,
    "lat"                 => $lat,
    "lng"                 => $lng,
    "address"             => $address,
    "phones_allowed"      => $phones_allowed,
    "supervisor_present"  => $supervisor_present,
    "status"              => "pending",  // lowercase to match your CHECK constraint
    "status_id"           => $status_id,
    "expo_push_token"     => $expo_push_token  // ✅ Store push token
];

error_log("Attempting to insert into admin_signup_requests: " . json_encode($insertData));

$insertRes = file_get_contents(
    SUPABASE_URL . "/rest/v1/admin_signup_requests",
    false,
    stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => array_merge($headers, ["Prefer: return=representation"]),
            "content" => json_encode($insertData)
        ]
    ])
);

// Check if insertion was successful
if ($insertRes === false) {
    error_log("Failed to insert into admin_signup_requests");
    echo json_encode([
        "success" => false,
        "error" => "Database error. Please try again."
    ]);
    exit;
}

$insertedData = json_decode($insertRes, true);
error_log("Insert result: " . json_encode($insertedData));

// Verify data was inserted
if (!$insertedData || count($insertedData) === 0) {
    error_log("Insertion failed - no data returned");
    echo json_encode([
        "success" => false,
        "error" => "Failed to save registration. Please try again."
    ]);
    exit;
}

error_log("✅ Successfully inserted into admin_signup_requests with status_id: $status_id");

/* ---------- SEND EMAIL TO SUPER ADMIN ---------- */
try {
    $approveLink = "http://10.79.133.61/bus-app-api/approve.php?status_id=$status_id";
    $rejectLink  = "http://10.79.133.61/bus-app-api/reject.php?status_id=$status_id";

    $mail->addAddress("ajaymg137@gmail.com");
    $mail->Subject = "New Admin Approval Request - $college_name";

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2E1A8A; border-bottom: 3px solid #6A3BEF; padding-bottom: 10px;'>
                🔔 New College Admin Registration
            </h2>

            <div style='background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #333;'>College Information</h3>
                <table style='width: 100%;'>
                    <tr>
                        <td style='padding: 8px 0;'><strong>College Name:</strong></td>
                        <td style='padding: 8px 0;'>$college_name</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong>College Code:</strong></td>
                        <td style='padding: 8px 0;'>$college_code</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong>Email:</strong></td>
                        <td style='padding: 8px 0;'>$email</td>
                    </tr>
                </table>
            </div>

            <div style='background: #f0f7ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #333;'>Location Details</h3>
                <p><strong>Address:</strong> $address</p>
                <p><strong>Coordinates:</strong> Lat: $lat, Lng: $lng</p>
            </div>

            <div style='background: #fff3e0; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #333;'>Policy Information</h3>
                <p><strong>Student Phones Allowed:</strong> " . ($phones_allowed ? "✅ Yes" : "❌ No") . "</p>
                <p><strong>Supervisor Present:</strong> " . ($supervisor_present ? "✅ Yes" : "❌ No") . "</p>
            </div>

            <div style='background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #4CAF50;'>
                <p style='margin: 0;'><strong>Status ID:</strong> <span style='font-size: 18px; color: #2196F3;'>$status_id</span></p>
            </div>

            <div style='margin: 30px 0; text-align: center;'>
                <a href='$approveLink' style='background: #4CAF50; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px;'>
                    ✅ APPROVE
                </a>
                <a href='$rejectLink' style='background: #f44336; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px;'>
                    ❌ REJECT
                </a>
            </div>

            <p style='color: #666; font-size: 12px; margin-top: 30px;'>
                This is an automated notification from the Bus Tracking System.
            </p>
        </div>
    ";

    $mail->send();
    error_log("✅ Approval email sent to super admin");
} catch (Exception $e) {
    error_log("❌ Failed to send approval email: " . $e->getMessage());
}

/* ---------- SEND CONFIRMATION EMAIL TO COLLEGE ADMIN ---------- */
try {
    $mail->clearAddresses();
    $mail->addAddress($email);
    $mail->Subject = "Registration Submitted - Status ID: $status_id";

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2E1A8A; border-bottom: 3px solid #6A3BEF; padding-bottom: 10px;'>
                ✅ Registration Request Submitted
            </h2>

            <p>Dear <strong>$college_name</strong> Administrator,</p>

            <p>Thank you for registering with our Bus Tracking System. Your registration request has been successfully submitted and is currently under review.</p>

            <div style='background: #E3F2FD; padding: 20px; border-radius: 8px; border-left: 4px solid #2196F3; margin: 25px 0;'>
                <h3 style='margin-top: 0; color: #1976D2;'>🔑 Your Status ID</h3>
                <p style='font-size: 28px; font-weight: bold; color: #1976D2; margin: 15px 0; letter-spacing: 2px;'>
                    $status_id
                </p>
                <p style='font-size: 13px; color: #555; margin-bottom: 0;'>
                    ⚠️ Please save this ID. You can use it to check your registration status.
                </p>
            </div>

            <div style='background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #333;'>📋 Submitted Details</h3>
                <table style='width: 100%;'>
                    <tr>
                        <td style='padding: 8px 0;'><strong>College Name:</strong></td>
                        <td style='padding: 8px 0;'>$college_name</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong>College Code:</strong></td>
                        <td style='padding: 8px 0;'>$college_code</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong>Email:</strong></td>
                        <td style='padding: 8px 0;'>$email</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong>Location:</strong></td>
                        <td style='padding: 8px 0;'>$address</td>
                    </tr>
                </table>
            </div>

            <div style='background: #fff3e0; padding: 20px; border-radius: 8px;'>
                <h3 style='margin-top: 0; color: #333;'>⏳ What Happens Next?</h3>
                <ol style='line-height: 1.8;'>
                    <li>Our team will review your registration details (typically within 24-48 hours)</li>
                    <li>You will receive an email notification once your account is <strong>approved</strong> or if we need additional information</li>
                    <li>After approval, you can log in to the admin portal using your registered email and password</li>
                </ol>
            </div>

            <div style='background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0; color: #2e7d32;'>
                    <strong>💡 Tip:</strong> Keep this email for your records. You'll need your Status ID if you contact support.
                </p>
            </div>

            <p style='color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;'>
                <strong>Note:</strong> This is an automated email. Please do not reply to this message.<br>
                If you have any questions, please contact our support team.<br><br>
                If you did not register for this service, please ignore this email.
            </p>
        </div>
    ";

    $mail->send();
    error_log("✅ Confirmation email sent to college admin: $email");
} catch (Exception $e) {
    error_log("❌ Failed to send confirmation email: " . $e->getMessage());
    // Don't fail the signup if confirmation email fails
}

/* ---------- SUCCESS RESPONSE ---------- */
echo json_encode([
    "success" => true,
    "status_id" => $status_id,
    "message" => "Registration submitted successfully"
]);