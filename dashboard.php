<?php
// dashboard.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);

if (!$user) {
    respond(false, 'Unauthorized', null, 401);
}

// respond(true, 'Dashboard data', [
//     'name' => $user['name'],
//     'totalBalance' => $totalBalance,
//     'monthlyExpense' => $monthlyExpense,
//     'activeGoals' => $activeGoals
// ]);


// total balance (sum of accounts)
$stmt = $conn->prepare("SELECT COALESCE(SUM(balance),0) AS total_balance FROM accounts WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['total_balance'];

// recent transactions
$tx = [];
$stmt = $conn->prepare("SELECT id, amount, type, category, description, tx_date FROM transactions WHERE user_id = ? ORDER BY tx_date DESC, created_at DESC LIMIT 10");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $tx[] = $r;

// todays habits (count active)
$habits = [];
$hStmt = $conn->prepare("SELECT id, name, goal_per_day FROM habits WHERE user_id = ? AND active = 1");
$hStmt->bind_param('i', $user['id']);
$hStmt->execute();
$hres = $hStmt->get_result();
while($h = $hres->fetch_assoc()) $habits[] = $h;

// goals progress summary
$goals = [];
$gStmt = $conn->prepare("
    SELECT id, title, target_amount, current_amount 
    FROM goals 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 2
");
$gStmt->bind_param('i', $user['id']);
$gStmt->execute();
$gres = $gStmt->get_result();
while($g = $gres->fetch_assoc()) $goals[] = $g;

respond(true, 'dashboard', [
    'name' => $user['name'],
    'total_balance' => (float)$balance,
    'recent_transactions' => $tx,
    'active_habits' => $habits,
    'goals' => $goals
]);

