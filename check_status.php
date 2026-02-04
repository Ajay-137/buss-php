<?php
require 'config.php';
require 'mailer.php';

$input = json_decode(file_get_contents("php://input"), true);
$code = strtoupper(trim($input['status_code'] ?? ''));

if (strlen($code) !== 7) {
  echo json_encode(['error' => 'Invalid code']);
  exit;
}

$headers = [
  'apikey: ' . SUPABASE_SERVICE_KEY,
  'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
  'Content-Type: application/json'
];

$response = file_get_contents(
  SUPABASE_URL . "/rest/v1/admin_applications?status_code=eq.$code&select=status,email",
  false,
  stream_context_create(['http' => ['header' => $headers]])
);

$app = json_decode($response, true)[0] ?? null;
if (!$app) {
  echo json_encode(['status' => 'not_found']);
  exit;
}

// notify YOU that status was checked
sendMail(
  ADMIN_EMAIL,
  'Admin Status Checked',
  "Status code <b>$code</b> checked.<br>Status: <b>{$app['status']}</b>"
);

echo json_encode(['status' => $app['status']]);
