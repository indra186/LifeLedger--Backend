<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$strategy = $_GET['strategy'] ?? '';
$goal_id = (int)($_GET['goal_id'] ?? 0);


if (!$strategy || $goal_id <= 0) {
    respond(false, 'strategy and goal_id required', null, 400);
}

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(success) as success_count,
        SUM(success * (1 / (1 + TIMESTAMPDIFF(DAY, created_at, NOW())))) as weighted_success,
        SUM(1 / (1 + TIMESTAMPDIFF(DAY, created_at, NOW()))) as weighted_total
    FROM strategy_feedback
    WHERE user_id = ? AND goal_id = ? AND strategy = ?
");

$stmt->bind_param("iis", $user['id'], $goal_id, $strategy);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

$total = (int)$result['total'];
$weighted_success = (double)$result['weighted_success'];
$weighted_total = (double)$result['weighted_total'];

$score = 0.5;

if ($weighted_total > 0) {
    $score = ($weighted_success + 1) / ($weighted_total + 2);
}

respond(true, "strategy score", [
    "score" => $score,
    "samples" => $total
]);