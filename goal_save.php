<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);

if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$input = get_json_input();

$title  = trim($input['title'] ?? '');
$target = isset($input['target_amount']) ? (float)$input['target_amount'] : 0;
$date   = $input['target_date'] ?? null;
$currentamount = isset($input['current_amount']) ? (float)$input['current_amount'] : 0;

// ✅ BASIC VALIDATION
if ($title === '' || $target <= 0) {
    respond(false, 'title and target_amount required', null, 400);
}

// ✅ DATE VALIDATION
if (!$date) {
    respond(false, 'target_date is required', null, 400);
}

$selectedDate = strtotime($date);
if (!$selectedDate) {
    respond(false, 'invalid date format', null, 400);
}

$today = strtotime(date('Y-m-d'));

if ($selectedDate < $today) {
    respond(false, "Target date cannot be in the past", null, 400);
}

// ✅ INSERT
$stmt = $conn->prepare("
    INSERT INTO goals (user_id, title, target_amount, current_amount, target_date)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(
    'isdds',
    $user['id'],
    $title,
    $target,
    $currentamount,
    $date
);

$stmt->execute();

respond(true, 'goal created', [
    'goal_id' => $stmt->insert_id
]);