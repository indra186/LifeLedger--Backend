<?php
header('Content-Type: application/json');
require_once __DIR__ . '/helpers.php';

$conn = db_connect();
$user = validate_token($conn);

file_put_contents("debug_log.txt", "\n\n---- NEW REQUEST ----\n", FILE_APPEND);
file_put_contents("debug_log.txt", file_get_contents("php://input"), FILE_APPEND);
file_put_contents("debug_log.txt", "\nUser: " . json_encode($user) . "\n", FILE_APPEND);

if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$data = json_decode(file_get_contents("php://input"), true);

$goal = $data['goal'];
$finance = $data['finance'];
$recovery = $data['recovery'];

// ✅ IF ALREADY ON TRACK → CLEAR TASKS
if ($goal['actual'] >= $goal['required']) {

    $conn->query("
        DELETE FROM agent_tasks 
        WHERE user_id = {$user['id']} 
        AND goal_id = {$goal['id']}
    ");

    respond(true, "No action needed", []);
}


// 🚫 PREVENT DUPLICATE QUEUE and pusihing into queue
$check = $conn->query("
SELECT id FROM agent_queue 
WHERE user_id = {$user['id']} 
AND goal_id = {$goal['id']} 
AND status = 'PENDING'
LIMIT 1
");

if ($check->num_rows > 0) {
    respond(true, "Already processing", [
        "status" => "processing"
    ]);
}

$payload = json_encode($data);

$stmt->bind_param(
    "iis",
    $user['id'],
    $goal['id'],
    $payload
);

$stmt->execute();

// ✅ FAST RESPONSE (NO WAITING FOR AI)
respond(true, "Request accepted. AI processing in background.", [
    "status" => "queued"
]);

// 🔥 CONTINUE BACKGROUND (optional but safe)
ignore_user_abort(true);
set_time_limit(0);