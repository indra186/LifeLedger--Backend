<?php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);

if (!$user) {
    respond(false, 'Unauthorized', null, 401);
}

$input = get_json_input();

$goal_id = (int)$input['goal_id'];
$amount  = (float)$input['amount'];
$account_id = (int)$input['account_id'];

if ($goal_id <= 0 || $amount <= 0) {
    respond(false, 'invalid input', null, 400);
}

// 1. Check account balance
$stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $account_id, $user['id']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res || $res['balance'] < $amount) {
    respond(false, 'insufficient balance', null, 400);
}

// 2. Deduct from account
$stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
$stmt->bind_param('di', $amount, $account_id);
$stmt->execute();

// 3. Add to goal
$stmt = $conn->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE id = ?");
$stmt->bind_param('di', $amount, $goal_id);
$stmt->execute();

// 4. Insert history
$stmt = $conn->prepare(
    "INSERT INTO goal_progress (goal_id, user_id, amount_added, date_added)
     VALUES (?, ?, ?, NOW())"
);
$stmt->bind_param('iid', $goal_id, $user['id'], $amount);
$stmt->execute();

respond(true, 'amount added to goal');