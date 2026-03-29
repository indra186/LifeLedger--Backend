<?php
// report_monthly.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-t', strtotime($start)); // last day of month

$stmt = $conn->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id = ? AND tx_date BETWEEN ? AND ? GROUP BY type");
$stmt->bind_param('iss', $user['id'], $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$summary = ['income'=>0.00,'expense'=>0.00];
while($r = $res->fetch_assoc()){
    $summary[$r['type']] = (float)$r['total'];
}

// spending by category
$stmt2 = $conn->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id = ? AND type='expense' AND tx_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC LIMIT 10");
$stmt2->bind_param('iss', $user['id'], $start, $end);
$stmt2->execute();
$res2 = $stmt2->get_result();
$bycat = [];
while($r = $res2->fetch_assoc()) $bycat[] = $r;

respond(true, 'monthly report', ['month'=>$month,'year'=>$year,'summary'=>$summary,'by_category'=>$bycat]);
