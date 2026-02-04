<?php
require 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

$role = $data['role']; // student | driver | supervisor
$regId = trim($data['reg_id']);
$dob = $data['dob']; // DD-MM-YYYY

if (!in_array($role, ['student','driver','supervisor']) || !$regId || !$dob) {
  echo json_encode(['error' => 'Invalid input']);
  exit;
}

// convert DOB
$dobObj = DateTime::createFromFormat('d-m-Y', $dob);
if (!$dobObj) {
  echo json_encode(['error' => 'Invalid DOB']);
  exit;
}

$dobSql = $dobObj->format('Y-m-d');

$headers = [
  'apikey: ' . SUPABASE_SERVICE_KEY,
  'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
];

$url = SUPABASE_URL . "/rest/v1/users?"
     . "role=eq.$role&reg_id=eq.$regId&dob=eq.$dobSql&status=eq.active&select=id";

$res = file_get_contents($url, false, stream_context_create([
  'http' => ['header' => $headers]
]));

$user = json_decode($res, true)[0] ?? null;

if (!$user) {
  echo json_encode(['error' => 'Invalid credentials']);
  exit;
}

echo json_encode([
  'success' => true,
  'role' => $role,
  'user_id' => $user['id']
]);
