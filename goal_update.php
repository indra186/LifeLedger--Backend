<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$input = get_json_input();

$id     = (int)($input['id'] ?? 0);
$title  = trim($input['title'] ?? '');
$target = isset($input['target_amount']) ? (float)$input['target_amount'] : 0;
$date   = $input['target_date'] ?? null;

if ($id <= 0 || $title === '' || $target <= 0) {
    respond(false, 'invalid input', null, 400);
}

$stmt = $conn->prepare(
    "UPDATE goals
     SET title = ?, target_amount = ?, target_date = ?
     WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('sdsii', $title, $target, $date, $id, $user['id']);
$stmt->execute();

respond(true, 'goal updated');
