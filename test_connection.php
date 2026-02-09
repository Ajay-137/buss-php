<?php
// TEST FILE - Place this in your bus-app-api folder as test_connection.php
// Access it via: http://10.87.56.61/bus-app-api/test_connection.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

echo "=== CONNECTION TEST ===\n\n";

// 1. Check if config.php exists
if (!file_exists('config.php')) {
    die(json_encode(["error" => "config.php not found"]));
}
require 'config.php';
echo "✓ config.php loaded\n";

// 2. Check constants
echo "SUPABASE_URL: " . (defined('SUPABASE_URL') ? SUPABASE_URL : "NOT DEFINED") . "\n";
echo "SUPABASE_SERVICE_KEY: " . (defined('SUPABASE_SERVICE_KEY') ? "EXISTS" : "NOT DEFINED") . "\n\n";

// 3. Test Supabase connection
function testSupabaseRequest($endpoint) {
    $url = SUPABASE_URL . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

echo "=== TESTING SUPABASE CONNECTION ===\n\n";

// Test drivers table
$result = testSupabaseRequest("/rest/v1/drivers?select=id,name,college_code&limit=5");
echo "HTTP Code: " . $result['http_code'] . "\n";
echo "Error: " . ($result['error'] ?: "None") . "\n";
echo "Response: " . substr($result['response'], 0, 200) . "\n\n";

if ($result['http_code'] == 200) {
    $drivers = json_decode($result['response'], true);
    echo "✓ Successfully connected to Supabase\n";
    echo "Found " . count($drivers) . " drivers\n\n";
    
    if (!empty($drivers)) {
        echo "Sample driver:\n";
        print_r($drivers[0]);
    }
} else {
    echo "✗ Failed to connect to Supabase\n";
    echo "Full response: " . $result['response'] . "\n";
}

echo "\n=== TEST COMPLETE ===\n";