<?php
// settings_update.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();
$notifications = isset($input['notifications']) ? json_encode($input['notifications']) : null;
$language = isset($input['language']) ? $input['language'] : null;
$timezone = isset($input['timezone']) ? $input['timezone'] : null;

// upsert settings row
// Try update first, if zero affected then insert (simple approach)
$stmt = $conn->prepare("SELECT id FROM settings WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
if($res && $res->num_rows > 0){
    // update
    $q = "UPDATE settings SET ";
    $parts = [];
    $types = '';
    $vals = [];
    if($notifications !== null){ $parts[] = "notifications = ?"; $types .= 's'; $vals[] = $notifications; }
    if($language !== null){ $parts[] = "language = ?"; $types .= 's'; $vals[] = $language; }
    if($timezone !== null){ $parts[] = "timezone = ?"; $types .= 's'; $vals[] = $timezone; }
    if(empty($parts)) respond(true, 'nothing to update', null, 200);
    $q .= implode(', ', $parts) . " WHERE user_id = ?";
    $types .= 'i'; $vals[] = $user['id'];
    $stmt2 = $conn->prepare($q);
    $stmt2->bind_param($types, ...$vals);
    $stmt2->execute();
    respond(true, 'settings updated', null, 200);
} else {
    // insert
    $stmt2 = $conn->prepare("INSERT INTO settings (user_id, notifications, language, timezone) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param('isss', $user['id'], $notifications, $language, $timezone);
    if($stmt2->execute()) respond(true, 'settings saved', null, 201);
    respond(false, 'failed to save settings', null, 500);
}