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
    $emailBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2E1A8A;'>🔔 New College Admin Registration</h2>
            <p><strong>College:</strong> $college_name ($college_code)</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Address:</strong> $address</p>
            <p><strong>Phones Allowed:</strong> " . ($phones_allowed ? "✅ Yes" : "❌ No") . "</p>
            <p><strong>Supervisor Present:</strong> " . ($supervisor_present ? "✅ Yes" : "❌ No") . "</p>
            <p><strong>Status ID:</strong> $status_id</p>
            <div style='margin: 30px 0; text-align: center;'>
                <a href='$approveLink' style='background:#4CAF50;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;margin:10px;display:inline-block;'>✅ APPROVE</a>
                <a href='$rejectLink' style='background:#f44336;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;margin:10px;display:inline-block;'>❌ REJECT</a>
            </div>
        </div>
    ";

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "sender" => ["name" => "Bus App", "email" => "ajaymg137@gmail.com"],
            "to" => [["email" => "ajaymg137@gmail.com"]],
            "subject" => "New Admin Approval Request - $college_name",
            "htmlContent" => $emailBody
        ]),
        CURLOPT_HTTPHEADER => ["api-key: " . getenv('BREVO_API_KEY'), "Content-Type: application/json"]
    ]);
    $brevoCode = curl_getinfo(curl_exec($ch) ? $ch : $ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log("Super admin email brevo code: $brevoCode");
} catch (Exception $e) {
    error_log("❌ Failed to send approval email: " . $e->getMessage());
}

/* ---------- SEND CONFIRMATION EMAIL TO COLLEGE ADMIN ---------- */
try {
    $confirmBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2E1A8A;'>✅ Registration Request Submitted</h2>
            <p>Dear <strong>$college_name</strong> Administrator,</p>
            <p>Your registration has been submitted and is under review.</p>
            <div style='background:#E3F2FD;padding:20px;border-radius:8px;border-left:4px solid #2196F3;margin:25px 0;'>
                <h3 style='color:#1976D2;margin-top:0;'>🔑 Your Status ID</h3>
                <p style='font-size:28px;font-weight:bold;color:#1976D2;letter-spacing:2px;'>$status_id</p>
                <p style='font-size:13px;color:#555;'>⚠️ Save this ID to check your registration status.</p>
            </div>
            <p><strong>College:</strong> $college_name | <strong>Code:</strong> $college_code</p>
            <p><strong>Email:</strong> $email | <strong>Location:</strong> $address</p>
            <p>Our team will review within 24-48 hours. You'll be notified once approved.</p>
        </div>
    ";

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "sender" => ["name" => "Bus App", "email" => "ajaymg137@gmail.com"],
            "to" => [["email" => $email]],
            "subject" => "Registration Submitted - Status ID: $status_id",
            "htmlContent" => $confirmBody
        ]),
        CURLOPT_HTTPHEADER => ["api-key: " . getenv('BREVO_API_KEY'), "Content-Type: application/json"]
    ]);
    curl_exec($ch);
    curl_close($ch);
    error_log("✅ Confirmation email sent to: $email");
} catch (Exception $e) {
    error_log("❌ Failed to send confirmation email: " . $e->getMessage());
}

/* ---------- SUCCESS RESPONSE ---------- */
echo json_encode([
    "success" => true,
    "status_id" => $status_id,
    "message" => "Registration submitted successfully"
]);