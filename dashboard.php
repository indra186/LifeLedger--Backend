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
$currentMonth = date('m');
$currentYear = date('Y');

/*
|--------------------------------------------------------------------------
| Monthly expense
|--------------------------------------------------------------------------
*/

$monthlyExpenseStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM transactions
    WHERE user_id = ?
    AND type = 'expense'
    AND MONTH(tx_date) = ?
    AND YEAR(tx_date) = ?
");

$monthlyExpenseStmt->bind_param(
    'iii',
    $user['id'],
    $currentMonth,
    $currentYear
);

$monthlyExpenseStmt->execute();

$monthlyExpense =
    $monthlyExpenseStmt
        ->get_result()
        ->fetch_assoc()['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| Monthly transaction count
|--------------------------------------------------------------------------
*/

$monthlyTransactionStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM transactions
    WHERE user_id = ?
    AND MONTH(tx_date) = ?
    AND YEAR(tx_date) = ?
");

$monthlyTransactionStmt->bind_param(
    'iii',
    $user['id'],
    $currentMonth,
    $currentYear
);

$monthlyTransactionStmt->execute();

$monthlyTransactions =
    $monthlyTransactionStmt
        ->get_result()
        ->fetch_assoc()['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| Today's habits progress
|--------------------------------------------------------------------------
*/

$today =
    date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Total habits scheduled today
|--------------------------------------------------------------------------
*/

$currentDay =
    date('D');

/*
|--------------------------------------------------------------------------
| Total habits scheduled today
|--------------------------------------------------------------------------
*/

$todayHabitsStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM habits
    WHERE user_id = ?
    AND active = 1
    AND (
        frequency = 'daily'
        OR (
            frequency = 'custom'
            AND selected_days LIKE ?
        )
    )
");

$dayPattern =
    "%" . $currentDay . "%";

$todayHabitsStmt->bind_param(
    'is',
    $user['id'],
    $dayPattern
);

$todayHabitsStmt->execute();

$totalTodayHabits =
    $todayHabitsStmt
        ->get_result()
        ->fetch_assoc()['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| Completed habits today
|--------------------------------------------------------------------------
*/

$completedHabitsStmt = $conn->prepare("
    SELECT COUNT(DISTINCT habit_id) AS total
    FROM habit_logs
    WHERE completed_date = ?
    AND habit_id IN (
        SELECT id
        FROM habits
        WHERE user_id = ?
        AND active = 1
    )
");

$completedHabitsStmt->bind_param(
    'si',
    $today,
    $user['id']
);

$completedHabitsStmt->execute();

$completedTodayHabits =
    $completedHabitsStmt
        ->get_result()
        ->fetch_assoc()['total'] ?? 0;

        $currentMonthBalanceStmt = $conn->prepare("
    SELECT COALESCE(SUM(balance),0) AS total
    FROM accounts
    WHERE user_id = ?
");

/*
|--------------------------------------------------------------------------
| Current month net flow
|--------------------------------------------------------------------------
*/

$currentNetStmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN type = 'income'
                    THEN amount
                    ELSE -amount
                END
            ),
        0) AS total
    FROM transactions
    WHERE user_id = ?
    AND MONTH(tx_date) = ?
    AND YEAR(tx_date) = ?
");

$currentNetStmt->bind_param(
    'iii',
    $user['id'],
    $currentMonth,
    $currentYear
);

$currentNetStmt->execute();

$currentMonthNet =
    $currentNetStmt
        ->get_result()
        ->fetch_assoc()['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| Last month net flow
|--------------------------------------------------------------------------
*/

$lastMonth =
    date('m', strtotime('-1 month'));

$lastMonthYear =
    date('Y', strtotime('-1 month'));

$lastNetStmt = $conn->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN type = 'income'
                    THEN amount
                    ELSE -amount
                END
            ),
        0) AS total
    FROM transactions
    WHERE user_id = ?
    AND MONTH(tx_date) = ?
    AND YEAR(tx_date) = ?
");

$lastNetStmt->bind_param(
    'iii',
    $user['id'],
    $lastMonth,
    $lastMonthYear
);

$lastNetStmt->execute();

$lastMonthNet =
    $lastNetStmt
        ->get_result()
        ->fetch_assoc()['total'] ?? 0;

/*
|--------------------------------------------------------------------------
| Growth calculation
|--------------------------------------------------------------------------
*/

$balanceDifference =
    $currentMonthNet -
    $lastMonthNet;

$percentageChange = 0;

if($lastMonthNet != 0) {

    $percentageChange =
        (
            $balanceDifference /
            abs($lastMonthNet)
        ) * 100;
}
respond(true, 'dashboard', [

    'name' => $user['name'],

    'total_balance' =>
        (float)$balance,

    'monthly_expense' =>
        (float)$monthlyExpense,

    'monthly_transaction_count' =>
        (int)$monthlyTransactions,

    'today_habits_total' =>
    (int)$totalTodayHabits,

'today_habits_completed' =>
    (int)$completedTodayHabits,

    'recent_transactions' =>
        $tx,

    'active_habits' =>
        $habits,

    'goals' =>
        $goals,

    'balance_change_percent' =>
        round($percentageChange, 1),

    'balance_change_positive' =>
        $percentageChange >= 0,
    'balance_change_amount' =>
        round($balanceDifference, 2),
]);