<?php
require_once __DIR__.'/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();

$category = trim($input['category'] ?? '');
$limit    = isset($input['limit_amount']) ? (float)$input['limit_amount'] : 0;
$alert = !empty($input['alert_enabled']) ? 80 : 0;
$id       = isset($input['id']) ? (int)$input['id'] : 0;

if ($category === '' || $limit <= 0) {
    respond(false, 'category and limit_amount required', null, 400);
}

if ($id > 0) {
    $stmt = $conn->prepare("
        UPDATE budgets 
        SET category = ?, limit_amount = ?, alert_threshold = ?
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("sdiii", $category, $limit, $alert, $id, $user['id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        respond(true, "budget updated", null);
    } else {
        respond(false, "nothing updated", null, 400);
    }
}
else {
    $month = isset($input['month']) ? (int)$input['month'] : 0;
    $year  = isset($input['year']) ? (int)$input['year'] : 0;

    $stmt = $conn->prepare("
        INSERT INTO budgets (
            user_id,
            category,
            limit_amount,
            alert_threshold,
            month,
            year
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isdiii",
        $user['id'],
        $category,
        $limit,
        $alert,
        $month,
        $year
    );

    if ($stmt->execute()) {
        respond(true, "budget created", ["id" => $conn->insert_id], 201);
    } else {
        respond(false, $stmt->error, null, 500);
    }
}
