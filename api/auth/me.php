<?php
// api/auth/me.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

$user = $_SESSION['user'];
// Ensure expected keys exist
$user += [
  'full_name' => $user['full_name'] ?? null,
  'role' => $user['role'] ?? 'employee',
  'phone' => $user['phone'] ?? null,
  'employee_id' => $user['employee_id'] ?? null,
];
echo json_encode(['ok' => true, 'user' => $user]);


