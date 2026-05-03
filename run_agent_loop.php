<?php
require_once 'helpers.php';

$conn = db_connect();

$query = $conn->query("
SELECT * FROM agent_tasks 
WHERE status IN ('PENDING','FAILED') 
AND retries < max_retries
");

while ($task = $query->fetch_assoc()) {

    $success = false;

    // SIMULATED EXECUTION LOGIC
    if ($task['strategy'] == "START_SAVING") {
        $success = rand(0,1);
    }

    if ($task['strategy'] == "CUT_SPENDING") {
        $success = rand(0,1);
    }

    $newStatus = $success ? "COMPLETED" : "FAILED";

    $stmt = $conn->prepare("
        UPDATE agent_tasks
        SET status=?, retries=retries+1
        WHERE id=?
    ");

    $stmt->bind_param("si", $newStatus, $task['id']);
    $stmt->execute();
}