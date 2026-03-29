<?php
// payments_list.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$stmt = $conn->prepare(
    "SELECT id, amount, method, status, upi_id, card_last4, created_at
     FROM payments
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT ?"
);
$stmt->bind_param('ii', $user['id'], $limit);

$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
respond(true, 'payments', $out);
