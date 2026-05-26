<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user)
    respond(false, 'unauthorized', null, 401);

$category = $_GET['category'] ?? '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$stmt = $conn->prepare("
    SELECT
        id,
        category,
        amount,
        description,
        tx_date

    FROM transactions

    WHERE
        user_id = ?
        AND type = 'expense'
        AND category = ?
        AND MONTH(tx_date) = ?
        AND YEAR(tx_date) = ?

    ORDER BY tx_date DESC
");

$stmt->bind_param(
    "isii",
    $user['id'],
    $category,
    $month,
    $year
);

$stmt->execute();

$res = $stmt->get_result();

$out = [];

while($r = $res->fetch_assoc()) {
    $out[] = $r;
}

respond(true, 'transactions', $out);