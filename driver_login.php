<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// Log function
function logDebug($message, $data = null) {
    $logMessage = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $logMessage .= ': ' . json_encode($data);
    }
    error_log($logMessage . PHP_EOL, 3, __DIR__ . '/debug.log');
}

logDebug('=== DRIVER LOGIN START ===');

require 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

logDebug('Config loaded');
logDebug('SUPABASE_URL', SUPABASE_URL);

// Add supabase helper function if not in config.php
if (!function_exists('supabaseRequest')) {
    logDebug('supabaseRequest function NOT found in config, defining it now');
    
    function supabaseRequest($endpoint, $method = 'GET', $data = null) {
        logDebug('supabaseRequest called', ['endpoint' => $endpoint, 'method' => $method]);
        
        $url = SUPABASE_URL . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        logDebug('Supabase response', ['http_code' => $httpCode, 'curl_error' => $curlError]);
        
        if ($httpCode >= 400) {
            logDebug('Supabase error response', $response);
            return [];
        }
        
        return json_decode($response, true) ?: [];
    }
} else {
    logDebug('supabaseRequest function already exists in config');
}

$rawInput = file_get_contents("php://input");
logDebug('Raw input', $rawInput);

$data = json_decode($rawInput, true);
logDebug('Decoded data', $data);

$college_code = trim($data['college_code'] ?? '');
$name         = trim($data['name'] ?? '');
$password     = $data['password'] ?? '';
$pushToken    = $data['expo_push_token'] ?? null;

logDebug('Extracted fields', [
    'college_code' => $college_code,
    'name' => $name,
    'password_length' => strlen($password),
    'has_push_token' => !empty($pushToken)
]);

if (!$college_code || !$name || !$password) {
    logDebug('Missing fields validation failed');
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit;
}

try {
    logDebug('Attempting to fetch driver from Supabase');
    
    /* Fetch driver */
    $drivers = supabaseRequest(
        "/rest/v1/drivers?college_code=eq.$college_code&name=eq.$name&select=*"
    );

    logDebug('Drivers fetched', ['count' => count($drivers), 'data' => $drivers]);

    if (empty($drivers)) {
        logDebug('No driver found with these credentials');
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    $driver = $drivers[0];
    logDebug('Driver found', ['id' => $driver['id'], 'has_password' => !empty($driver['password'])]);

    /* Verify password */
    $passwordMatch = password_verify($password, $driver['password']);
    logDebug('Password verification', ['match' => $passwordMatch]);
    
    if (!$passwordMatch) {
        logDebug('Password verification failed');
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit;
    }

    logDebug('Password verified successfully');

    /* Store push token if provided */
    if ($pushToken) {
        logDebug('Storing push token');
        supabaseRequest(
            "/rest/v1/drivers?id=eq." . $driver['id'],
            "PATCH",
            ["expo_push_token" => $pushToken]
        );
    }

    $responseData = [
        "success" => true,
        "driver" => [
            "id"   => $driver['id'],
            "lat"  => (float)$driver['lat'],
            "lng"  => (float)$driver['lng'],
            "ride" => $driver['ride'] ?? false
        ]
    ];

    logDebug('Sending success response', $responseData);
    echo json_encode($responseData);
    logDebug('=== DRIVER LOGIN SUCCESS ===');

} catch (Exception $e) {
    logDebug('EXCEPTION CAUGHT', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    echo json_encode([
        "success" => false, 
        "message" => "Server error: " . $e->getMessage()
    ]);
}

logDebug('=== DRIVER LOGIN END ===');