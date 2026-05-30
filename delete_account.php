<?php

require_once __DIR__ . '/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if (!$user) {

    respond(false, "Unauthorized", null, 401);
}

$userId = $user['id'];

/*
DELETE related data first
*/

$conn->query(
    "DELETE FROM habits WHERE user_id = $userId"
);

$conn->query(
    "DELETE FROM goals WHERE user_id = $userId"
);

$conn->query(
    "DELETE FROM transactions WHERE user_id = $userId"
);

$conn->query(
    "DELETE FROM budgets WHERE user_id = $userId"
);

/*
DELETE user
*/

$stmt = $conn->prepare(
    "DELETE FROM users WHERE id = ?"
);

$stmt->bind_param("i", $userId);

if ($stmt->execute()) {

    respond(true, "Account deleted");
}

respond(false, "Failed to delete", null, 500);