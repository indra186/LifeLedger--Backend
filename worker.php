<?php
require_once 'helpers.php';

$conn = db_connect();

file_put_contents("worker_log.txt", "\n---- WORKER RUN ----\n", FILE_APPEND);

// 🔥 GET QUEUE
$res = $conn->query("
SELECT * FROM agent_queue 
WHERE status = 'PENDING'
ORDER BY created_at ASC
LIMIT 5
");

while ($row = $res->fetch_assoc()) {

    $queueId = $row['id'];
    $userId = $row['user_id'];
    $goalId = $row['goal_id'];

    file_put_contents("worker_log.txt", "\nProcessing goal: {$goalId}\n", FILE_APPEND);

    $data = json_decode($row['payload'], true);

    if (!$data || !isset($data['goal']) || !isset($data['finance'])) {
        file_put_contents("worker_log.txt", "Invalid payload\n", FILE_APPEND);
        continue;
    }

    $goal = $data['goal'];
    $finance = $data['finance'];

    // 🔥 PROMPT
    $prompt = "
You are a financial AI agent.

STRICT RULES:
- Output ONLY valid JSON
- No explanation

FORMAT:
{
  \"strategy\": \"<START_SAVING | CUT_SPENDING | INCREASE_INCOME | OPTIMIZE_SAVING>\",
  \"tasks\": [
    {
      \"type\": \"<same as strategy>\",
      \"action\": \"<clear actionable sentence with ₹>\",
      \"amount\": <number>,
      \"category\": \"<optional>\"
    }
  ]
}

GOAL:
Required/day: {$goal['required']}
Actual/day: {$goal['actual']}
Remaining: {$goal['remaining']}
Days left: {$goal['daysLeft']}

FINANCE:
Income: {$finance['income']}
Expense: {$finance['expense']}
Top category: {$finance['topCategory']}
Surplus: {$finance['surplus']}
";

    // 🔥 CALL OLLAMA
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => "http://192.168.2.100:11434/api/generate",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "llama3",
            "prompt" => $prompt,
            "stream" => false
        ])
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        file_put_contents("worker_log.txt", "\nCURL ERROR: $error\n", FILE_APPEND);
        curl_close($ch);
        continue;
    }

    curl_close($ch);

    file_put_contents("worker_log.txt", "\nOllama Raw Response: $response\n", FILE_APPEND);

    $result = json_decode($response, true);
    $content = trim($result['response'] ?? "");

    if (!$content) {
        file_put_contents("worker_log.txt", "Empty LLM response\n", FILE_APPEND);
    }

    // 🔥 SAFE PARSE
   
    $content = trim($result['response'] ?? "");

    // FIX invalid JSON (missing quotes)
    $content = preg_replace('/"strategy":\s*([A-Z_]+)/', '"strategy":"$1"', $content);
    $content = preg_replace('/"type":\s*([A-Z_]+)/', '"type":"$1"', $content);

    $parsed = json_decode($content, true);

    if ($parsed === null) {
        preg_match('/\{.*\}/s', $content, $matches);
        $parsed = json_decode($matches[0] ?? "{}", true);
    }

    if ($parsed === null) {
        preg_match('/\{.*\}/s', $content, $matches);
        $parsed = json_decode($matches[0] ?? "{}", true);
    }

    file_put_contents("worker_log.txt", "\nParsed JSON: " . json_encode($parsed) . "\n", FILE_APPEND);

    // 🔥 FALLBACK
    if (!$parsed || !isset($parsed['tasks']) || empty($parsed['tasks'])) {
        $parsed = [
            "strategy" => "CUT_SPENDING",
            "tasks" => [
                [
                    "type" => "CUT_SPENDING",
                    "action" => "Reduce food spending to ₹200/day",
                    "amount" => 200,
                    "category" => "Food"
                ]
            ]
        ];
    }

    // 🔥 CLEAR OLD TASKS
    $conn->query("
    DELETE FROM agent_tasks 
    WHERE user_id = $userId AND goal_id = $goalId
    ");

    foreach ($parsed['tasks'] as $task) {

        $type = $task['type'] ?? "GENERAL";
        $action = $task['action'] ?? "NO_ACTION";

        $metadata = json_encode([
            "amount" => $task['amount'] ?? 0,
            "category" => $task['category'] ?? null
        ]);

        $stmt = $conn->prepare("
            INSERT INTO agent_tasks 
            (user_id, goal_id, strategy, action, task_type, metadata, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ");

        $priority = match($parsed['strategy']) {
            "INCREASE_INCOME" => 3,
            "CUT_SPENDING" => 2,
            default => 1
        };

        $stmt->bind_param(
            "iissssi",
            $userId,
            $goalId,
            $parsed['strategy'],
            $action,
            $type,
            $metadata,
            $priority
        );

        $stmt->execute();

        file_put_contents("worker_log.txt", "Inserted task: {$action}\n", FILE_APPEND);
    }

    // ✅ MARK DONE
    $conn->query("
    UPDATE agent_queue 
    SET status = 'DONE'
    WHERE id = $queueId
    ");
}

echo "Worker executed";