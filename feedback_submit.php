<?php
// feedback_submit.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);

$input = get_json_input();
$type = $input['type'] ?? 'general';
$subject = $input['subject'] ?? null;
$details = $input['details'] ?? null;
$user_id = $user ? $user['id'] : null;

if(!$subject || !$details) respond(false, 'subject and details required', null, 400);

$stmt = $conn->prepare("INSERT INTO feedback (user_id, type, subject, details) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $user_id, $type, $subject, $details);
if($stmt->execute()) respond(true, 'feedback submitted', ['id'=>$conn->insert_id], 201);
else respond(false, 'failed', null, 500);
