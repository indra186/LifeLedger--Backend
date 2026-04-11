<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$goal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($goal_id <= 0) {
    respond(false, 'goal id required', null, 400);
}

$stmt = $conn->prepare(
    "SELECT id, title, target_amount,current_amount, target_date
     FROM goals
     WHERE id = ? AND user_id = ? LIMIT 1"
);
$stmt->bind_param('ii', $goal_id, $user['id']);
$stmt->execute();

$res = $stmt->get_result();
if ($res->num_rows === 0) {
    respond(false, 'goal not found', null, 404);
}

respond(true, 'goal fetched', $res->fetch_assoc());
