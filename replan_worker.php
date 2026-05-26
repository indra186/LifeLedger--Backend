<?php
require_once 'helpers.php';

$conn = db_connect();

$res = $conn->query("SELECT * FROM agent_replan_queue LIMIT 5");

while ($row = $res->fetch_assoc()) {

    $goalId = $row['goal_id'];
    $userId = $row['user_id'];

    // 🔥 GOAL
    $goal = $conn->query("
        SELECT target_amount, current_amount, target_date
        FROM goals WHERE id = $goalId
    ")->fetch_assoc();

    if (!$goal) continue;

    // 🔥 HISTORY
    $historyRes = $conn->query("
        SELECT amount_added FROM goal_progress WHERE goal_id = $goalId
    ");

    $sum = 0;
    $count = 0;

    while ($h = $historyRes->fetch_assoc()) {
        $sum += $h['amount_added'];
        $count++;
    }

    $actual = $count > 0 ? $sum / $count : 0;

    // 🔥 FINANCE
    $txRes = $conn->query("
        SELECT * FROM transactions WHERE user_id = $userId
    ");

    $income = 0;
    $expense = 0;
    $categoryMap = [];

    while ($t = $txRes->fetch_assoc()) {
        if ($t['type'] == 'income') $income += $t['amount'];
        if ($t['type'] == 'expense') {
            $expense += $t['amount'];
            $categoryMap[$t['category']] =
                ($categoryMap[$t['category']] ?? 0) + $t['amount'];
        }
    }

    arsort($categoryMap);
    $topCategory = array_key_first($categoryMap) ?? "None";
    $surplus = $income - $expense;

    // 🔥 GOAL CALC
    $remaining = $goal['target_amount'] - $goal['current_amount'];
    $daysLeft = max(1, (strtotime($goal['target_date']) - time()) / 86400);
    $required = $remaining / $daysLeft;

    // 🔥 CALL AI
    $payload = json_encode([
        "goal" => [
            "id" => $goalId,
            "required" => $required,
            "actual" => $actual,
            "remaining" => $remaining,
            "daysLeft" => (int)$daysLeft
        ],
        "finance" => [
            "income" => $income,
            "expense" => $expense,
            "topCategory" => $topCategory,
            "surplus" => $surplus
        ],
        "recovery" => [
            "suggestion" => "Adjust plan dynamically"
        ]
    ]);

   $ch = curl_init("http://localhost/lifeledger/ai_agent.php");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer YOUR_TOKEN_HERE",
        "Content-Type: application/json"
    ]);

    curl_exec($ch);
    curl_close($ch);

    // 🔥 REMOVE FROM QUEUE
    $conn->query("DELETE FROM agent_replan_queue WHERE id={$row['id']}");
}

echo "Replan executed";