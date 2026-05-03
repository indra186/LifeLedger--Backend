<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$data = json_decode(file_get_contents("php://input"), true);

$goal_id = (int)$data['goal_id'];
$strategy = $data['strategy'];
$actual = (double)$data['actual_saved'];
$expected = (double)$data['expected_saved'];
$success = $data['success'] ? 1 : 0;
if (!$goal_id || !$strategy) {
    respond(false, "invalid input", null, 400);
}
if ($actual < 0 || $expected < 0) {
    respond(false, "invalid values", null, 400);
}

$stmt = $conn->prepare("
    INSERT INTO strategy_feedback
    (user_id, goal_id, strategy, success, actual_saved, expected_saved)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iisidd",
    $user['id'],
    $goal_id,
    $strategy,
    $success,
    $actual,
    $expected
);

$stmt->execute();

respond(true, "feedback stored");