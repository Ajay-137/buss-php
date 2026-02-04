<?php
/**
 * Push Notification Utility
 * 
 * Reusable functions for sending Expo push notifications
 * Usage: require 'push_notifications.php'; then call sendPushNotification()
 */

/**
 * Send push notification via Expo
 * 
 * @param string $expoPushToken - The Expo push token (starts with ExponentPushToken[...])
 * @param string $title - Notification title
 * @param string $body - Notification message
 * @param array $data - Optional extra data to send with notification
 * @return array - Response with success status and message
 */
function sendPushNotification($expoPushToken, $title, $body, $data = []) {
    // Validate token format
    if (empty($expoPushToken) || !str_starts_with($expoPushToken, 'ExponentPushToken[')) {
        error_log("PUSH: Invalid token format: $expoPushToken");
        return [
            'success' => false,
            'error' => 'Invalid push token format'
        ];
    }

    // Prepare notification payload
    $notification = [
        'to' => $expoPushToken,
        'title' => $title,
        'body' => $body,
        'sound' => 'default',
        'priority' => 'high',
        'channelId' => 'default',
    ];

    // Add custom data if provided
    if (!empty($data)) {
        $notification['data'] = $data;
    }

    // Expo Push API endpoint
    $url = 'https://exp.host/--/api/v2/push/send';

    // Send request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Encoding: gzip, deflate',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    // Log the response
    error_log("PUSH SENT: code=$httpCode, response=" . json_encode($result));

    // Check for errors
    if ($httpCode !== 200) {
        error_log("PUSH ERROR: HTTP $httpCode - $response");
        return [
            'success' => false,
            'error' => "HTTP error: $httpCode",
            'response' => $result
        ];
    }

    // Check Expo's response
    if (isset($result['data'][0]['status']) && $result['data'][0]['status'] === 'error') {
        error_log("PUSH ERROR: " . json_encode($result['data'][0]));
        return [
            'success' => false,
            'error' => $result['data'][0]['message'] ?? 'Unknown error',
            'response' => $result
        ];
    }

    return [
        'success' => true,
        'message' => 'Notification sent successfully',
        'response' => $result
    ];
}

/**
 * Send batch push notifications (up to 100 at once)
 * 
 * @param array $notifications - Array of notification objects
 *   Each object should have: token, title, body, data (optional)
 * @return array - Response with results
 */
function sendBatchPushNotifications($notifications) {
    if (empty($notifications) || count($notifications) > 100) {
        return [
            'success' => false,
            'error' => 'Invalid batch size (max 100)'
        ];
    }

    $messages = [];
    foreach ($notifications as $notif) {
        $message = [
            'to' => $notif['token'],
            'title' => $notif['title'],
            'body' => $notif['body'],
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'default',
        ];

        if (!empty($notif['data'])) {
            $message['data'] = $notif['data'];
        }

        $messages[] = $message;
    }

    $url = 'https://exp.host/--/api/v2/push/send';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("BATCH PUSH: code=$httpCode, count=" . count($messages));

    return [
        'success' => $httpCode === 200,
        'response' => json_decode($response, true)
    ];
}

/**
 * Preset notification templates for common scenarios
 */
class NotificationTemplates {
    
    public static function approvalGranted($collegeName) {
        return [
            'title' => '✅ Registration Approved!',
            'body' => "Congratulations! Your registration for $collegeName has been approved. You can now login to the admin portal.",
            'data' => [
                'type' => 'approval',
                'action' => 'approved',
                'screen' => 'AdminLogin'
            ]
        ];
    }

    public static function approvalRejected($collegeName, $reason = null) {
        $body = "Your registration for $collegeName was not approved.";
        if ($reason) {
            $body .= " Reason: $reason";
        }
        $body .= " Please contact support for more information.";

        return [
            'title' => '❌ Registration Not Approved',
            'body' => $body,
            'data' => [
                'type' => 'approval',
                'action' => 'rejected',
                'screen' => 'Support'
            ]
        ];
    }

    public static function busLocationUpdate($busNumber, $location) {
        return [
            'title' => "🚌 Bus $busNumber Location Update",
            'body' => "Bus is now at $location",
            'data' => [
                'type' => 'location_update',
                'bus_number' => $busNumber,
                'screen' => 'BusTracking'
            ]
        ];
    }

    public static function studentAbsent($studentName, $busNumber) {
        return [
            'title' => '⚠️ Student Absent',
            'body' => "$studentName was marked absent from Bus $busNumber",
            'data' => [
                'type' => 'attendance',
                'screen' => 'Attendance'
            ]
        ];
    }

    public static function emergencyAlert($message) {
        return [
            'title' => '🚨 EMERGENCY ALERT',
            'body' => $message,
            'data' => [
                'type' => 'emergency',
                'priority' => 'high',
                'screen' => 'Emergency'
            ]
        ];
    }

    public static function customNotification($title, $body, $data = []) {
        return [
            'title' => $title,
            'body' => $body,
            'data' => $data
        ];
    }
}