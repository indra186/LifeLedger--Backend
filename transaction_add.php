<?php
// transaction_add.php (corrected)
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();

// required fields
$amount = isset($input['amount']) ? $input['amount'] : null;
$type = isset($input['type']) ? $input['type'] : null;
$category = isset($input['category']) ? $input['category'] : null;
$description = isset($input['description']) ? $input['description'] : null;
$tx_date = trim($input['date'] ?? '');

if ($tx_date === '') {
    respond(false, 'date is required', null, 400);
}


// optional account_id
$account_id = isset($input['account_id']) && $input['account_id'] !== '' ? (int)$input['account_id'] : null;

// basic validation
if($amount === null || !is_numeric($amount) || !in_array($type, ['income','expense'])){
    respond(false, 'invalid payload: amount (numeric) and type (income|expense) are required', null, 400);
}
$amount = (float)$amount;

// if account_id provided, check it exists for this user
if($account_id !== null){
    $check = $conn->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ? LIMIT 1");
    $check->bind_param('ii', $account_id, $user['id']);
    $check->execute();
    $cres = $check->get_result();
    if(!$cres || $cres->num_rows === 0){
        respond(false, 'account_id not found for this user', null, 400);
    }
    $check->close();
}

// Build insert SQL depending on whether account_id is present
if($account_id !== null){
    $sql = "INSERT INTO transactions (user_id, account_id, amount, type, category, description, tx_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if(!$stmt) respond(false, 'prepare failed: '.$conn->error, null, 500);
    // types: user_id (i), account_id (i), amount (d), type (s), category (s), description (s), tx_date (s)
    $types = 'iidssss'; // i i d s s s s
    $stmt->bind_param($types,
        $user['id'],
        $account_id,
        $amount,
        $type,
        $category,
        $description,
        $tx_date
    );
} else {
    // no account_id -> insert NULL automatically by not including column
    $sql = "INSERT INTO transactions (user_id, amount, type, category, description, tx_date) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if(!$stmt) respond(false, 'prepare failed: '.$conn->error, null, 500);
    // types: user_id (i), amount (d), type (s), category (s), description (s), tx_date (s)
    $types = 'idssss'; // i d s s s s
    $stmt->bind_param($types,
        $user['id'],
        $amount,
        $type,
        $category,
        $description,
        $tx_date
    );
}

if($stmt->execute()){
    $tx_id = $conn->insert_id;
    // update account balance if linked
    if($account_id !== null){
        if($type === 'income'){
            $upd = $conn->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ? AND user_id = ?");
            $upd->bind_param('dii', $amount, $account_id, $user['id']);
            $upd->execute();
            $upd->close();
        } else {
            $upd = $conn->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ? AND user_id = ?");
            $upd->bind_param('dii', $amount, $account_id, $user['id']);
            $upd->execute();
            $upd->close();
        }
    }
    // update budget spent
if($type === 'expense'){
    $bud = $conn->prepare("
        UPDATE budgets 
        SET spent_amount = spent_amount + ? 
        WHERE user_id = ? AND category = ?
    ");
    $bud->bind_param('dis', $amount, $user['id'], $category);
    $bud->execute();
    $bud->close();
}
    $stmt->close();
    respond(true, 'transaction added', ['transaction_id' => $tx_id], 201);
} else {
    $err = $stmt->error ?: $conn->error;
    $stmt->close();
    respond(false, 'insert failed: '.$err, null, 500);
}
