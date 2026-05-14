<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user) {
    respond(false, 'unauthorized', null, 401);
}

$stmt = $conn->prepare("
    SELECT DISTINCT
        MONTH(tx_date) as month,
        YEAR(tx_date) as year
    FROM transactions
    WHERE user_id = ?
    ORDER BY year ASC, month ASC
");

$stmt->bind_param(
    "i",
    $user['id']
);

$stmt->execute();

$res = $stmt->get_result();

$out = [];

while($r = $res->fetch_assoc()) {
    $out[] = $r;
}

respond(true, 'available months', $out);