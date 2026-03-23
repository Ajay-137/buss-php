<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/secrets.php';

$input = json_decode(file_get_contents("php://input"), true);

$college_code = $input['college_code'] ?? null;
$name         = $input['name'] ?? null;
$password     = $input['password'] ?? null;
$lat          = $input['lat'] ?? null;
$lng          = $input['lng'] ?? null;

if (!$college_code || !$name || !$password || $lat === null || $lng === null) {
    echo json_encode(["success" => false, "error" => "Missing fields"]);
    exit;
}

$url = SUPABASE_URL . "/rest/v1/drivers";

$payload = json_encode([
    "college_code" => $college_code,
    "name"         => $name,
    "password"     => password_hash($password, PASSWORD_DEFAULT),
    "lat"          => $lat,
    "lng"          => $lng
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        "apikey: " . SUPABASE_SERVICE_KEY,
        "Authorization: Bearer " . SUPABASE_SERVICE_KEY,
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($http_code !== 201 && $http_code !== 200) {
    echo json_encode([
        "success"          => false,
        "error"            => "Supabase insert failed",
        "http_code"        => $http_code,
        "supabase_response"=> $response
    ]);
    exit;
}

echo json_encode(["success" => true, "data" => $data[0] ?? null]);