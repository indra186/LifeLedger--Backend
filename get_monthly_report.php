<?php

require_once __DIR__ . '/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if (!$user) {

    respond(false, 'Unauthorized', null, 401);
}

$month =
    intval($_GET['month'] ?? date('n'));

$year =
    intval($_GET['year'] ?? date('Y'));

$userId =
    $user['id'];

/*
|--------------------------------------------------------------------------
| DATE RANGE
|--------------------------------------------------------------------------
*/

$startDate =
    "$year-" .
    str_pad($month, 2, '0', STR_PAD_LEFT) .
    "-01";

$endDate =
    date(
        'Y-m-t',
        strtotime($startDate)
    );

/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$summaryQuery = $conn->prepare("

    SELECT

        SUM(
            CASE
                WHEN LOWER(type) = 'income'
                THEN amount
                ELSE 0
            END
        ) as income,

        SUM(
            CASE
                WHEN LOWER(type) = 'expense'
                THEN amount
                ELSE 0
            END
        ) as expense

    FROM transactions

    WHERE user_id = ?

    AND tx_date BETWEEN ? AND ?

");

$summaryQuery->bind_param(
    "iss",
    $userId,
    $startDate,
    $endDate
);

$summaryQuery->execute();

$summary =
    $summaryQuery
        ->get_result()
        ->fetch_assoc();

$income =
    floatval($summary['income'] ?? 0);

$expense =
    floatval($summary['expense'] ?? 0);

$savings =
    $income - $expense;

/*
|--------------------------------------------------------------------------
| CATEGORY SPENDING + BUDGET DATA
|--------------------------------------------------------------------------
*/

$categoryQuery = $conn->prepare("

    SELECT

        t.category,

        SUM(t.amount) as total,

        b.limit_amount as budget_limit

    FROM transactions t

    LEFT JOIN budgets b
        ON b.user_id = t.user_id
        AND LOWER(b.category) = LOWER(t.category)
        AND b.month = ?
        AND b.year = ?

    WHERE t.user_id = ?

    AND LOWER(t.type) = 'expense'

    AND t.tx_date BETWEEN ? AND ?

    GROUP BY t.category, b.limit_amount

    ORDER BY total DESC

");

$categoryQuery->bind_param(
    "iiiss",
    $month,
    $year,
    $userId,
    $startDate,
    $endDate
);

$categoryQuery->execute();

$categoryResult =
    $categoryQuery->get_result();

$categories = [];

$topCategory = "None";

$highest = 0;

while (
    $row = $categoryResult->fetch_assoc()
) {

    $total =
        floatval($row['total']);

    $budgetLimit =
        floatval($row['budget_limit'] ?? 0);

    $usagePercent = 0;

    if($budgetLimit > 0) {

        $usagePercent =
            round(
                ($total / $budgetLimit) * 100
            );
    }

    $categories[] = [

        "category" =>
            $row['category'],

        "total" =>
            $total,

        "budget_limit" =>
            $budgetLimit,

        "usage_percent" =>
            $usagePercent
    ];

    if($total > $highest) {

        $highest = $total;

        $topCategory =
            $row['category'];
    }
}
/*
|--------------------------------------------------------------------------
| SPENDING PATTERN
|--------------------------------------------------------------------------
*/

$currentMonth =
    date('n');

$currentYear =
    date('Y');

$currentDay =
    date('j');

$isCurrentMonth =
    $month == $currentMonth &&
    $year == $currentYear;

$spendingPattern = [];

/*
|--------------------------------------------------------------------------
| CURRENT MONTH -> DAILY VIEW
|--------------------------------------------------------------------------
*/

if($isCurrentMonth) {

    $dailyQuery = $conn->prepare("

        SELECT

            tx_date,

            SUM(amount) as total

        FROM transactions

        WHERE user_id = ?

        AND LOWER(type) = 'expense'

        AND tx_date BETWEEN ? AND ?

        GROUP BY tx_date

        ORDER BY tx_date

    ");

    $dailyQuery->bind_param(
        "iss",
        $userId,
        $startDate,
        $endDate
    );

    $dailyQuery->execute();

    $dailyResult =
        $dailyQuery
            ->get_result();

    $dailyTotals = [];

    while(
        $row =
            $dailyResult->fetch_assoc()
    ) {

        $day =
            intval(
                date(
                    'j',
                    strtotime($row['tx_date'])
                )
            );

        $dailyTotals[$day] =
            floatval($row['total']);
    }

    /*
    |--------------------------------------------------------------------------
    | INCLUDE ALL DAYS
    |--------------------------------------------------------------------------
    */

    for(
        $day = 1;
        $day <= $currentDay;
        $day++
    ) {

        $date =
            "$year-" .
            str_pad($month, 2, '0', STR_PAD_LEFT) .
            "-" .
            str_pad($day, 2, '0', STR_PAD_LEFT);

        $label =
            date(
                'D d',
                strtotime($date)
            );

        $spendingPattern[] = [

            "label" =>
                $label,

            "total" =>
                $dailyTotals[$day] ?? 0
        ];
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | PREVIOUS MONTHS -> WEEKLY VIEW
    |--------------------------------------------------------------------------
    */

    $weeklyQuery = $conn->prepare("

        SELECT

            DAY(tx_date) as day_num,

            amount

        FROM transactions

        WHERE user_id = ?

        AND LOWER(type) = 'expense'

        AND tx_date BETWEEN ? AND ?

    ");

    $weeklyQuery->bind_param(
        "iss",
        $userId,
        $startDate,
        $endDate
    );

    $weeklyQuery->execute();

    $weeklyResult =
        $weeklyQuery
            ->get_result();

    $lastDayOfMonth =
    intval(
        date(
            't',
            strtotime($startDate)
        )
    );

$lastWeekLabel =
    "29-" . $lastDayOfMonth;

$weeklyTotals = [

    "1-7" => 0,
    "8-14" => 0,
    "15-21" => 0,
    "22-28" => 0,
    $lastWeekLabel => 0
];

    while(
        $row =
            $weeklyResult->fetch_assoc()
    ) {

        $day =
            intval($row['day_num']);

        $amount =
            floatval($row['amount']);

        if($day <= 7) {

            $weeklyTotals["1-7"] +=
                $amount;

        } elseif($day <= 14) {

            $weeklyTotals["8-14"] +=
                $amount;

        } elseif($day <= 21) {

            $weeklyTotals["15-21"] +=
                $amount;

        } elseif($day <= 28) {

            $weeklyTotals["22-28"] +=
                $amount;

        } else {

            $weeklyTotals[$lastWeekLabel] +=
                $amount;
        }
    }

    foreach(
        $weeklyTotals as
        $label => $amount
    ) {

        $spendingPattern[] = [

            "label" =>
                $label,

            "total" =>
                $amount
        ];
    }
}

/*
|--------------------------------------------------------------------------
| EMPTY STATE FALLBACK
|--------------------------------------------------------------------------
*/

if(empty($categories)) {

    $categories = [];
}

if(empty($spendingPattern)) {

    $spendingPattern = [];
}

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

respond(

    true,

    "Monthly report",

    [

        "summary" => [

            "income" =>
                $income,

            "expense" =>
                $expense,

            "savings" =>
                $savings,

            "top_category" =>
                $topCategory
        ],

        "categories" =>
            $categories,

        "spending_pattern" =>
            $spendingPattern
    ]
);