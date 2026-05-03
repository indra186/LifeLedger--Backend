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

// 1. Get account balance
$stmt = $conn->prepare("SELECT balance FROM accounts WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $account_id, $user['id']);
$stmt->execute();
$acc = $stmt->get_result()->fetch_assoc();

if (!$acc || $acc['balance'] < $amount) {
    respond(false, 'insufficient balance', null, 400);
}

// 2. Get goal details (IMPORTANT ADDITION)
$stmt = $conn->prepare("SELECT current_amount, target_amount FROM goals WHERE id = ?");
$stmt->bind_param('i', $goal_id);
$stmt->execute();
$goal = $stmt->get_result()->fetch_assoc();

if (!$goal) {
    respond(false, 'goal not found', null, 404);
}

$current = (float)$goal['current_amount'];
$target  = (float)$goal['target_amount'];

$remaining = $target - $current;

// 3. Decide how much to use
if ($remaining <= 0) {
    respond(false, 'goal already completed', null, 400);
}

if ($amount > $remaining) {
    $used = $remaining;
    $extra = $amount - $remaining;
} else {
    $used = $amount;
    $extra = 0;
}

//  4. Deduct ONLY used amount from account
$stmt = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
$stmt->bind_param('di', $used, $account_id);
$stmt->execute();

// NOTE: extra is NOT deducted at all → stays in account

// 5. Add ONLY used amount to goal
$stmt = $conn->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE id = ?");
$stmt->bind_param('di', $used, $goal_id);
$stmt->execute();

// 6. Insert history ONLY used amount
$stmt = $conn->prepare(
    "INSERT INTO goal_progress (goal_id, user_id, amount_added, date_added)
     VALUES (?, ?, ?, NOW())"
);
$stmt->bind_param('iid', $goal_id, $user['id'], $used);
$stmt->execute();

// 7. Response
respond(true, 'amount added to goal', [
    'used_amount' => $used,
    'extra_amount' => $extra
]);