<?php
require_once __DIR__.'/helpers.php';

$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$stmt = $conn->prepare("
    SELECT
        b.id,
        b.category,
        b.limit_amount,
        b.alert_threshold,
        b.created_at,
        b.month,
        b.year,

        IFNULL(
            (
                SELECT SUM(t.amount)
                FROM transactions t
                WHERE
                    t.user_id = b.user_id
                    AND t.category = b.category
                    AND t.type = 'expense'
                    AND MONTH(t.tx_date) = b.month
                    AND YEAR(t.tx_date) = b.year
            ),
            0
        ) AS spent_amount

    FROM budgets b

    WHERE
        b.user_id = ?
        AND b.month = ?
        AND b.year = ?
");

$stmt->bind_param(
    "iii",
    $user['id'],
    $month,
    $year
);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;

respond(true, 'budgets', $out);
