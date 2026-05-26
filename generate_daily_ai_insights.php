<?php

require_once 'helpers.php';

$conn = db_connect();

if (!$conn) {

    die('Database connection failed');
}

/*
|--------------------------------------------------------------------------
| GET ALL USERS
|--------------------------------------------------------------------------
*/

$users = $conn->query("

    SELECT id

    FROM users

");

if (!$users) {

    die('Failed to fetch users');
}

/*
|--------------------------------------------------------------------------
| LOOP USERS
|--------------------------------------------------------------------------
*/

while ($user = $users->fetch_assoc()) {

    $userId =
        (int)$user['id'];

    $today =
        date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | PREVENT DUPLICATE DAILY INSIGHT
    |--------------------------------------------------------------------------
    */

    $check =
        $conn->prepare("

            SELECT id

            FROM notifications

            WHERE
                user_id = ?
                AND type = 'ai_insight'
                AND DATE(created_at) = ?

            LIMIT 1
        ");

    if(!$check) {
        continue;
    }

    $check->bind_param(
        'is',
        $userId,
        $today
    );

    $check->execute();

    $exists =
        $check
            ->get_result()
            ->num_rows > 0;

    $check->close();

    if($exists) {

        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | TASK ANALYTICS
    |--------------------------------------------------------------------------
    */

    $taskQuery =
        $conn->prepare("

        SELECT

            COUNT(*) AS total_tasks,

            SUM(
                CASE
                    WHEN ti.completed = 1
                    THEN 1
                    ELSE 0
                END
            ) AS completed_tasks,

            SUM(
                CASE
                    WHEN ti.completed = 0
                    THEN 1
                    ELSE 0
                END
            ) AS pending_tasks,

            SUM(
                CASE
                    WHEN
                        t.priority = 'high'
                        AND ti.completed = 0
                    THEN 1
                    ELSE 0
                END
            ) AS high_priority_pending,

            SUM(
                CASE
                    WHEN
                        ti.completed = 1
                        AND DATE(ti.completed_at)
                            = ti.instance_date
                    THEN 1
                    ELSE 0
                END
            ) AS ontime_completed_tasks,

            SUM(
                CASE
                    WHEN
                        ti.completed = 1
                        AND DATE(ti.completed_at)
                            > ti.instance_date
                    THEN 1
                    ELSE 0
                END
            ) AS late_completed_tasks

        FROM task_instances ti

        INNER JOIN tasks t
        ON t.id = ti.task_id

        WHERE
            t.user_id = ?

        AND ti.instance_date >=
            DATE_SUB(CURDATE(), INTERVAL 7 DAY)

    ");

    if(!$taskQuery) {
        continue;
    }

    $taskQuery->bind_param(
        'i',
        $userId
    );

    $taskQuery->execute();

    $taskStats =
        $taskQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $taskQuery->close();

    $totalTasks =
        (int)(
            $taskStats['total_tasks']
            ?? 0
        );

    $completedTasks =
        (int)(
            $taskStats['completed_tasks']
            ?? 0
        );

    $pendingTasks =
        (int)(
            $taskStats['pending_tasks']
            ?? 0
        );

    $highPriorityPending =
        (int)(
            $taskStats['high_priority_pending']
            ?? 0
        );

    $ontimeCompletedTasks =
        (int)(
            $taskStats['ontime_completed_tasks']
            ?? 0
        );

    $lateCompletedTasks =
        (int)(
            $taskStats['late_completed_tasks']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | LAST WEEK TASK TREND
    |--------------------------------------------------------------------------
    */

    $lastWeekQuery =
        $conn->prepare("

        SELECT

            COUNT(*) AS total_tasks,

            SUM(
                CASE
                    WHEN ti.completed = 1
                    THEN 1
                    ELSE 0
                END
            ) AS completed_tasks

        FROM task_instances ti

        INNER JOIN tasks t
        ON t.id = ti.task_id

        WHERE
            t.user_id = ?

        AND ti.instance_date BETWEEN
            DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            AND DATE_SUB(CURDATE(), INTERVAL 8 DAY)

    ");

    if(!$lastWeekQuery) {
        continue;
    }

    $lastWeekQuery->bind_param(
        'i',
        $userId
    );

    $lastWeekQuery->execute();

    $lastWeekStats =
        $lastWeekQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $lastWeekQuery->close();

    $lastWeekTotal =
        (int)(
            $lastWeekStats['total_tasks']
            ?? 0
        );

    $lastWeekCompleted =
        (int)(
            $lastWeekStats['completed_tasks']
            ?? 0
        );

    $lastWeekRate = 0;

    if($lastWeekTotal > 0) {

        $lastWeekRate =
            round(
                (
                    $lastWeekCompleted /
                    $lastWeekTotal
                ) * 100
            );
    }

    /*
    |--------------------------------------------------------------------------
    | TASK COMPLETION RATE
    |--------------------------------------------------------------------------
    */

    $taskCompletionRate = 0;

    if($totalTasks > 0) {

        $taskCompletionRate =
            round(
                (
                    $completedTasks /
                    $totalTasks
                ) * 100
            );
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCTIVITY TREND
    |--------------------------------------------------------------------------
    */

    $productivityTrend = 'stable';

    if(
        $taskCompletionRate >
        $lastWeekRate + 10
    ) {

        $productivityTrend =
            'improving';

    } elseif(
        $taskCompletionRate <
        $lastWeekRate - 10
    ) {

        $productivityTrend =
            'declining';
    }

    /*
    |--------------------------------------------------------------------------
    | HABIT ANALYTICS
    |--------------------------------------------------------------------------
    */

    $habitQuery =
        $conn->prepare("

        SELECT

            id,
            frequency,
            selected_days

        FROM habits

        WHERE
            user_id = ?
            AND active = 1

    ");

    if(!$habitQuery) {
        continue;
    }

    $habitQuery->bind_param(
        'i',
        $userId
    );

    $habitQuery->execute();

    $habitResult =
        $habitQuery
            ->get_result();

    $totalHabits = 0;

    $expectedHabitLogs = 0;

    while(
        $habit =
            $habitResult->fetch_assoc()
    ) {

        $totalHabits++;

        $frequency =
            $habit['frequency'];

        $selectedDays =
            $habit['selected_days'];

        /*
        |--------------------------------------------------------------------------
        | DAILY HABITS
        |--------------------------------------------------------------------------
        */

        if(
            $frequency == 'daily'
        ) {

            $expectedHabitLogs += 7;
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTOM HABITS
        |--------------------------------------------------------------------------
        */

        elseif(
            $frequency == 'custom'
        ) {

            if(
                !empty($selectedDays)
            ) {

                $days =
                    explode(
                        ',',
                        $selectedDays
                    );

                $expectedHabitLogs +=
                    count($days);
            }
        }
    }

    $habitQuery->close();

    /*
    |--------------------------------------------------------------------------
    | HABIT LOGS THIS WEEK
    |--------------------------------------------------------------------------
    */

    $habitLogsQuery =
        $conn->prepare("

        SELECT

            COUNT(*) AS logs

        FROM habit_logs hl

        INNER JOIN habits h
        ON h.id = hl.habit_id

        WHERE
            h.user_id = ?

        AND hl.completed_date >=
            DATE_SUB(CURDATE(), INTERVAL 7 DAY)

    ");

    if(!$habitLogsQuery) {
        continue;
    }

    $habitLogsQuery->bind_param(
        'i',
        $userId
    );

    $habitLogsQuery->execute();

    $habitLogsStats =
        $habitLogsQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $habitLogsQuery->close();

    $habitLogsCount =
        (int)(
            $habitLogsStats['logs']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | HABIT ENGAGEMENT RATE
    |--------------------------------------------------------------------------
    */

    $habitEngagementRate = 0;

    if(
        $expectedHabitLogs > 0
    ) {

        $habitEngagementRate =
            round(
                (
                    $habitLogsCount /
                    $expectedHabitLogs
                ) * 100
            );
    }

    /*
    |--------------------------------------------------------------------------
    | HABIT TREND
    |--------------------------------------------------------------------------
    */

    $habitTrendQuery =
        $conn->prepare("

        SELECT

            SUM(
                CASE
                    WHEN hl.completed_date >=
                        DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    THEN 1
                    ELSE 0
                END
            ) AS current_week_logs,

            SUM(
                CASE
                    WHEN hl.completed_date BETWEEN
                        DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                        AND DATE_SUB(CURDATE(), INTERVAL 8 DAY)
                    THEN 1
                    ELSE 0
                END
            ) AS last_week_logs

        FROM habit_logs hl

        INNER JOIN habits h
        ON h.id = hl.habit_id

        WHERE h.user_id = ?

    ");

    if(!$habitTrendQuery) {
        continue;
    }

    $habitTrendQuery->bind_param(
        'i',
        $userId
    );

    $habitTrendQuery->execute();

    $habitTrendStats =
        $habitTrendQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $habitTrendQuery->close();

    $currentWeekHabitLogs =
        (int)(
            $habitTrendStats['current_week_logs']
            ?? 0
        );

    $lastWeekHabitLogs =
        (int)(
            $habitTrendStats['last_week_logs']
            ?? 0
        );

    $habitTrend = 'stable';

    if(
        $currentWeekHabitLogs >
        $lastWeekHabitLogs
    ) {

        $habitTrend = 'improving';

    } elseif(
        $currentWeekHabitLogs <
        $lastWeekHabitLogs
    ) {

        $habitTrend = 'declining';
    }

    /*
    |--------------------------------------------------------------------------
    | WEAKEST HABIT
    |--------------------------------------------------------------------------
    */

    $weakHabitQuery =
        $conn->prepare("

        SELECT

            h.name,

            COUNT(hl.id) AS completions

        FROM habits h

        LEFT JOIN habit_logs hl
        ON h.id = hl.habit_id

        AND hl.completed_date >=
            DATE_SUB(CURDATE(), INTERVAL 7 DAY)

        WHERE h.user_id = ?

        GROUP BY h.id, h.name

        ORDER BY completions ASC

        LIMIT 1

    ");

    if(!$weakHabitQuery) {
        continue;
    }

    $weakHabitQuery->bind_param(
        'i',
        $userId
    );

    $weakHabitQuery->execute();

    $weakHabit =
        $weakHabitQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $weakHabitQuery->close();

    $weakestHabit =
        $weakHabit['name']
        ?? 'daily habits';

    /*
    |--------------------------------------------------------------------------
    | BUDGET ANALYTICS
    |--------------------------------------------------------------------------
    */

    $budgetQuery =
        $conn->prepare("

        SELECT

            COUNT(*) AS total_budgets,

            SUM(
                CASE
                    WHEN spent_amount >= limit_amount
                    THEN 1
                    ELSE 0
                END
            ) AS exceeded_budgets,

            MAX(
                (
                    spent_amount /
                    limit_amount
                ) * 100
            ) AS max_usage_percent

        FROM budgets

        WHERE
            user_id = ?

        AND month = MONTH(CURDATE())

        AND year = YEAR(CURDATE())

    ");

    if(!$budgetQuery) {
        continue;
    }

    $budgetQuery->bind_param(
        'i',
        $userId
    );

    $budgetQuery->execute();

    $budgetStats =
        $budgetQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $budgetQuery->close();

    $totalBudgets =
        (int)(
            $budgetStats['total_budgets']
            ?? 0
        );

    $exceededBudgets =
        (int)(
            $budgetStats['exceeded_budgets']
            ?? 0
        );

    $maxBudgetUsage =
        round(
            $budgetStats['max_usage_percent']
            ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | BUDGET RISK RATE
    |--------------------------------------------------------------------------
    */

    $budgetRiskRate = 0;

    if($totalBudgets > 0) {

        $budgetRiskRate =
            round(
                (
                    $exceededBudgets /
                    $totalBudgets
                ) * 100
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MOST OVERRUN CATEGORY
    |--------------------------------------------------------------------------
    */

    $budgetCategoryQuery =
        $conn->prepare("

        SELECT

            category,

            (
                spent_amount /
                limit_amount
            ) * 100 AS usage_percent

        FROM budgets

        WHERE
            user_id = ?

        AND limit_amount > 0

        ORDER BY usage_percent DESC

        LIMIT 1

    ");

    if(!$budgetCategoryQuery) {
        continue;
    }

    $budgetCategoryQuery->bind_param(
        'i',
        $userId
    );

    $budgetCategoryQuery->execute();

    $budgetCategory =
        $budgetCategoryQuery
            ->get_result()
            ->fetch_assoc() ?: [];

    $budgetCategoryQuery->close();

    $topBudgetCategory =
        $budgetCategory['category']
        ?? 'general spending';

    /*
    |--------------------------------------------------------------------------
    | DISCIPLINE SCORE
    |--------------------------------------------------------------------------
    */

    $disciplineScore =
        round(

            (
                ($taskCompletionRate * 0.4) +

                ($habitEngagementRate * 0.35) +

                (
                    (100 - $budgetRiskRate)
                    * 0.25
                )
            )
        );

    /*
    |--------------------------------------------------------------------------
    | STRENGTH ANALYSIS
    |--------------------------------------------------------------------------
    */

    $strengthLine =
        'Your strongest area is ';

    if(
        $habitEngagementRate >=
        $taskCompletionRate
    ) {

        $strengthLine .=
            'habit consistency';

    } elseif(
        $taskCompletionRate >=
        $budgetRiskRate
    ) {

        $strengthLine .=
            'task execution';

    } else {

        $strengthLine .=
            'financial discipline';
    }

    /*
    |--------------------------------------------------------------------------
    | WEAKNESS ANALYSIS
    |--------------------------------------------------------------------------
    */

    $weaknessLine =
        'your weakest area is ';

    if(
        $highPriorityPending >= 2
    ) {

        $weaknessLine .=
            'high-priority backlog';

    } elseif(
        $lateCompletedTasks >= 2
    ) {

        $weaknessLine .=
            'late task execution';

    } elseif(
        $exceededBudgets > 0
    ) {

        $weaknessLine .=
            'budget overspending';

    } else {

        $weaknessLine .=
            'consistency';
    }

    /*
    |--------------------------------------------------------------------------
    | AI PROMPT
    |--------------------------------------------------------------------------
    */

    $prompt = "

You are an elite behavioral intelligence engine.

Analyze the user's behavioral performance data and generate ONE highly personalized productivity insight.

TASK ANALYTICS
- Weekly completion rate: {$taskCompletionRate}%
- Pending tasks: {$pendingTasks}
- High priority pending tasks: {$highPriorityPending}
- On-time completed tasks: {$ontimeCompletedTasks}
- Late completed tasks: {$lateCompletedTasks}
- Productivity trend: {$productivityTrend}

HABIT ANALYTICS
- Habit engagement rate: {$habitEngagementRate}%
- Active habits: {$totalHabits}
- Habit trend: {$habitTrend}
- Weakest habit: {$weakestHabit}

BUDGET ANALYTICS
- Budget risk rate: {$budgetRiskRate}%
- Highest spending category: {$topBudgetCategory}
- Maximum budget usage: {$maxBudgetUsage}%

DISCIPLINE SCORE
- Overall discipline score: {$disciplineScore}/100

CONTEXT
{$strengthLine}.
{$weaknessLine}.

RULES

- Maximum 18 words
- Use very simple English
- Make it understandable in one glance
- Sound human and personal
- Mention one good thing and one weak thing
- No difficult words
- No motivational quotes
- No emojis
- No markdown
- No labels
- Return ONLY one sentence
- Make the user feel personally understood
- Make the insight understandable in one glance
- Avoid corporate or formal wording
- Sound natural and conversational

GOOD OUTPUT EXAMPLES

You are staying consistent with habits, but high-priority tasks are getting delayed too often.

Your task completion improved this week, but spending control is starting to slip.

Your routines are strong, though unfinished important tasks are slowing your progress.

Habit consistency is improving, but delayed work is creating unnecessary pressure.

You are managing tasks better this week, but budget control needs more attention.
";

    /*
    |--------------------------------------------------------------------------
    | OLLAMA REQUEST
    |--------------------------------------------------------------------------
    */

    $ollamaData = [

        'model' => 'llama3',

        'prompt' => $prompt,

        'stream' => false
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [

        CURLOPT_URL =>
            'http://localhost:11434/api/generate',

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS =>
            json_encode($ollamaData),

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],

        CURLOPT_TIMEOUT => 60
    ]);

    $result =
        curl_exec($ch);

    $curlError =
        curl_error($ch);

    curl_close($ch);

    /*
    |--------------------------------------------------------------------------
    | FALLBACK INSIGHT
    |--------------------------------------------------------------------------
    */

    $fallbackInsight =

        'Your routines are stable, but delayed execution on important tasks suggests consistency is becoming harder to sustain.';

    /*
    |--------------------------------------------------------------------------
    | HANDLE AI RESPONSE
    |--------------------------------------------------------------------------
    */

    if(
        !$result ||
        !empty($curlError)
    ) {

        $insight =
            $fallbackInsight;

    } else {

        $response =
            json_decode(
                $result,
                true
            );

        $insight =
            trim(
                $response['response']
                ?? ''
            );

        if(
            empty($insight)
        ) {

            $insight =
                $fallbackInsight;
        }

        $insight =
            trim(
                str_replace(

                    [
                        "Here's your insight:",
                        "Insight:",
                        "\"",
                        "\n",
                        "\r"
                    ],

                    '',

                    $insight
                )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SAFETY LIMIT
    |--------------------------------------------------------------------------
    */

    if(
        strlen($insight) > 120
    ) {

        $insight =
            substr(
                $insight,
                0,
                120
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $save =
        $conn->prepare("

        INSERT INTO notifications (

            user_id,
            title,
            message,
            type

        )

        VALUES (?, ?, ?, ?)

    ");

    if(!$save) {
        continue;
    }

    $title =
        'Daily Insight';

    $type =
        'ai_insight';

    $save->bind_param(

        'isss',

        $userId,

        $title,

        $insight,

        $type
    );

    $save->execute();

    $save->close();

    /*
    |--------------------------------------------------------------------------
    | SMALL DELAY
    |--------------------------------------------------------------------------
    */

    sleep(1);
}

echo 'AI insights generated successfully';