<?php
// Load from local secrets file if it exists (XAMPP local dev)
if (file_exists(__DIR__ . '/config/secrets.php')) {
    require_once __DIR__ . '/config/secrets.php';
} else {
    // Production (Render) - read directly from environment variables
    define('SUPABASE_URL', getenv('SUPABASE_URL'));
    define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY'));
    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL'));
    define('MAIL_USERNAME', getenv('MAIL_USERNAME'));
    define('MAIL_PASSWORD', getenv('MAIL_PASSWORD'));
}