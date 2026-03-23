<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/secrets.php';

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/
$admin_id = $_GET['admin_id'] ?? null;

if (!$admin_id) {
    echo json_encode([
        "success" => false,
        "error" => "Admin ID missing"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| SUPABASE REST QUERY
|--------------------------------------------------------------------------
| Table: admins
*/
$url = SUPABASE_URL . "/rest/v1/admins"
     . "?id=eq." . urlencode($admin_id)
     . "&select=id,college_code,college_name,email,lat,lng,supervisor_present,tracking";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: " . SUPABASE_SERVICE_KEY,
        "Authorization: Bearer " . SUPABASE_SERVICE_KEY,
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode([
        "success" => false,
        "error" => "Supabase request failed"
    ]);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($http_code !== 200 || empty($data)) {
    echo json_encode([
        "success" => false,
        "error" => "Admin not found"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/
$admin = $data[0];

echo json_encode([
    "success" => true,
    "data" => [
        "admin_id"           => (int)$admin['id'],
        "college_code"       => $admin['college_code'],
        "college_name"       => $admin['college_name'],
        "email"              => $admin['email'],
        "lat"                => (float)$admin['lat'],
        "lng"                => (float)$admin['lng'],
        "supervisor_present" => $admin['supervisor_present'] ?? false,
        "tracking"           => $admin['tracking'] ?? true
    ]
]);