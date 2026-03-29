<?php
// premium_plan.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
// no auth required for plans
$plans = [
    ['id'=>1,'name'=>'Monthly','price'=>10,'period'=>'month'],
    ['id'=>2,'name'=>'Annual','price'=>99,'period'=>'year']
];

respond(true, 'plans', $plans);
