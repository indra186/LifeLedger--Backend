<?php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);

if (!$user) {
    respond(false, 'Unauthorized', null, 401);
}

$input = get_json_input();
$goal_id = (int)$input['goal_id'];

if ($goal_id <= 0) {
    respond(false, 'invalid goal id', null, 400);
}

// delete goal (ONLY user's goal)
$stmt = $conn->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $goal_id, $user['id']);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    respond(true, 'goal deleted');
} else {
    respond(false, 'goal not found or not yours', null, 404);
}