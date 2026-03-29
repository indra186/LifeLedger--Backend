<?php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();

/* Accept Android field names */
$name = trim($input['name'] ?? '');
$type = strtolower(trim($input['type'] ?? 'bank'));
$balance = isset($input['balance']) ? (float)$input['balance'] : 0.00;

/* Normalize type to enum */
if($type === 'bank') $type = 'bank';
elseif($type === 'credit') $type = 'credit';
elseif($type === 'cash') $type = 'cash';
else $type = 'other';

if($name === ''){
    respond(false, 'Account name required', null, 400);
}

$stmt = $conn->prepare(
    "INSERT INTO accounts (user_id, account_name, type, balance)
     VALUES (?, ?, ?, ?)"
);

$stmt->bind_param('issd', $user['id'], $name, $type, $balance);

if($stmt->execute()){
    respond(true, 'account created', [
        'account_id' => $stmt->insert_id,
        'account_name' => $name,
        'type' => $type,
        'balance' => $balance
    ], 201);
} else {
    respond(false, 'failed to create account', null, 500);
}
