<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$goal_id = isset($_GET['goal_id']) ? (int)$_GET['goal_id'] : 0;
if ($goal_id <= 0) {
    respond(false, 'goal_id required', null, 400);
}

$stmt = $conn->prepare(
    "SELECT id, amount_added, date_added
     FROM goal_progress
     WHERE goal_id = ? AND user_id = ?
     ORDER BY date_added DESC"
);
$stmt->bind_param('ii', $goal_id, $user['id']);
$stmt->execute();

$res = $stmt->get_result();
$progress = [];

while ($row = $res->fetch_assoc()) {
    $progress[] = $row;
}

respond(true, 'goal progress', [
    'goal_id'  => $goal_id,
    'progress' => $progress
]);
