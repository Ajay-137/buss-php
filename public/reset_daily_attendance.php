<?php
/**
 * Daily Attendance Reset Script
 * Run this script at midnight (00:00) every day via cron job
 * 
 * Cron setup (Linux):
 * 0 0 * * * /usr/bin/php /path/to/reset_daily_attendance.php
 * 
 * Windows Task Scheduler:
 * Trigger: Daily at 00:00
 * Action: Start a program
 * Program: C:\xampp\php\php.exe
 * Arguments: C:\xampp\htdocs\bus-app-api\reset_daily_attendance.php
 */

require 'config.php';

if (!function_exists('supabaseRequest')) {
    function supabaseRequest($endpoint, $method = 'GET', $data = null) {
        $url = SUPABASE_URL . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true) ?: [];
    }
}

try {
    // Reset boarded_today flag for all students
    supabaseRequest(
        "/rest/v1/students",
        "PATCH",
        ["boarded_today" => false]
    );

    $logMessage = date('Y-m-d H:i:s') . " - Daily attendance reset completed\n";
    file_put_contents(__DIR__ . '/reset_log.txt', $logMessage, FILE_APPEND);
    
    echo "Reset completed successfully\n";

} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/reset_log.txt', $errorMessage, FILE_APPEND);
    
    echo "Reset failed: " . $e->getMessage() . "\n";
}