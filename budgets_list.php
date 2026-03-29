<?php
require_once __DIR__.'/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.category,
        b.limit_amount,
        IFNULL(SUM(t.amount),0) AS spent_amount,
        b.alert_threshold,
        b.period
    FROM budgets b
    LEFT JOIN transactions t 
        ON t.category = b.category 
       AND t.user_id = b.user_id
       AND t.type = 'expense'
    WHERE b.user_id = ?
    GROUP BY b.id
");

$stmt->bind_param("i", $user['id']);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;

respond(true, 'budgets', $out);
