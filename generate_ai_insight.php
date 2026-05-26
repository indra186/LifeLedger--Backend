<?php

require_once 'helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user){
    respond(false, 'Unauthorized', null, 401);
}

/*
|--------------------------------------------------------------------------
| TASK ANALYTICS
|--------------------------------------------------------------------------
*/

$taskQuery = $conn->prepare("

SELECT

    COUNT(*) as total_tasks,

    SUM(
        CASE
            WHEN ti.completed = 1
            THEN 1
            ELSE 0
        END
    ) as completed_tasks

FROM task_instances ti

INNER JOIN tasks t
ON t.id = ti.task_id

WHERE t.user_id = ?

AND ti.instance_date >=
DATE_SUB(CURDATE(), INTERVAL 7 DAY)

");

$taskQuery->bind_param(
    "i",
    $user['id']
);

$taskQuery->execute();

$taskStats =
    $taskQuery
        ->get_result()
        ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| HABIT ANALYTICS
|--------------------------------------------------------------------------
*/

$habitQuery = $conn->prepare("

SELECT

    COUNT(*) as total_habits

FROM habits

WHERE user_id = ?

");

$habitQuery->bind_param(
    "i",
    $user['id']
);

$habitQuery->execute();

$habitStats =
    $habitQuery
        ->get_result()
        ->fetch_assoc();

/*
|--------------------------------------------------------------------------
| BUILD AI PROMPT
|--------------------------------------------------------------------------
*/

$prompt = "

User productivity summary:

Completed {$taskStats['completed_tasks']}
out of {$taskStats['total_tasks']} tasks
this week.

User has {$habitStats['total_habits']}
active habits.

Generate one short motivational
AI productivity insight.

";

/*
|--------------------------------------------------------------------------
| GEMINI API
|--------------------------------------------------------------------------
*/

// $apiKey = "YOUR_GEMINI_API_KEY";
$apiKey = "AIzaSyC5PmXJgan3AkRLEE5WDIViG0yCma9Br4U";

$url =
"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey";

$data = [

    "contents" => [[

        "parts" => [[

            "text" => $prompt
        ]]
    ]]
];

$options = [

    "http" => [

        "header" =>
            "Content-Type: application/json",

        "method" => "POST",

        "content" =>
            json_encode($data)
    ]
];

$context =
    stream_context_create($options);

$result =
    file_get_contents(
        $url,
        false,
        $context
    );

$response =
    json_decode($result, true);

$insight =
    $response['candidates'][0]
    ['content']['parts'][0]
    ['text']
    ?? "Keep improving daily.";

/*
|--------------------------------------------------------------------------
| SAVE INSIGHT
|--------------------------------------------------------------------------
*/

$save = $conn->prepare("

INSERT INTO notifications (

    user_id,
    title,
    message,
    type

)

VALUES (?, ?, ?, ?)

");

$title =
    "AI Insight";

$type =
    "ai_insight";

$save->bind_param(
    "isss",
    $user['id'],
    $title,
    $insight,
    $type
);

$save->execute();

respond(true, "AI insight generated", [

    "insight" => $insight
]);