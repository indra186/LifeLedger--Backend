<?php
// cashflow.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

// simple cashflow: last 6 months totals
$rows = [];
for($i=5;$i>=0;$i--){
    $dt = strtotime("-$i month");
    $m = date('m', $dt);
    $y = date('Y', $dt);
    $start = date('Y-m-01', $dt);
    $end = date('Y-m-t', $dt);
    $stmt = $conn->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id = ? AND tx_date BETWEEN ? AND ? GROUP BY type");
    $stmt->bind_param('iss', $user['id'], $start, $end);
    $stmt->execute();
    $res = $stmt->get_result();
    $inc = 0; $exp = 0;
    while($r=$res->fetch_assoc()){
        if($r['type']=='income') $inc = (float)$r['total'];
        else $exp = (float)$r['total'];
    }
    $rows[] = ['month'=>date('F Y', $dt),'income'=>$inc,'expense'=>$exp,'net'=>$inc-$exp];
}
respond(true, 'cashflow', $rows);
