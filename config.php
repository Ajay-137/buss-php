<?php
// Works both locally and on Render
if (file_exists(__DIR__ . '/config/secrets.php')) {
    require_once __DIR__ . '/config/secrets.php';        // Local XAMPP
} else {
    require_once __DIR__ . '/../config/secrets.php';     // Render (production)
}