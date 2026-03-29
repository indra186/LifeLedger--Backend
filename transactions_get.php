<?php
// transactions_get.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

// optional query params: start_date, end_date, type
$qs = $_GET;
$start = $qs['start_date'] ?? null;
$end = $qs['end_date'] ?? null;
$type = $qs['type'] ?? null;

$q = "SELECT 
    t.id,
    t.amount,
    t.type,
    t.category,
    t.description,
    t.tx_date,
    t.created_at,
    a.account_name
FROM transactions t
LEFT JOIN accounts a ON t.account_id = a.id
WHERE t.user_id = ?
";
$params = [$user['id']];
$types = 'i';

if($start){
    $q .= " AND tx_date >= ?";
    $types .= 's'; $params[] = $start;
}
if($end){
    $q .= " AND tx_date <= ?";
    $types .= 's'; $params[] = $end;
}
if($type && in_array($type, ['income','expense'])){
    $q .= " AND type = ?";
    $types .= 's'; $params[] = $type;
}
$q .= " ORDER BY tx_date DESC, created_at DESC LIMIT 500";

$stmt = $conn->prepare($q);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
respond(true, 'transactions', $out);
