<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user) {
    respond(false, 'unauthorized', null, 401);
}

$stmt = $conn->prepare("
    SELECT DISTINCT
        month,
        year
    FROM budgets
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