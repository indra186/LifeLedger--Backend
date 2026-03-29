<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$stmt = $conn->prepare(
    "SELECT id, title, target_amount, target_date
     FROM goals
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param('i', $user['id']);
$stmt->execute();

$res = $stmt->get_result();
$goals = [];

while ($row = $res->fetch_assoc()) {
    $goals[] = $row;
}

respond(true, 'goals list', ['goals' => $goals]);
