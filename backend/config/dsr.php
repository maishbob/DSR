<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Variance Thresholds
    |--------------------------------------------------------------------------
    |
    | Controls when a per-product stock variance triggers a WARNING or CRITICAL
    | status on the DSR. Both percentage and absolute (litres) conditions are
    | checked — whichever is worse wins.
    |
    | status   | meaning
    | ---------|----------------------------------------------------------
    | ok       | Variance within acceptable range — DSR can be approved
    | warning  | Variance notable — DSR can be approved, manager is informed
    | critical | Variance significant — approval BLOCKED unless override
    |           | with explicit reason is supplied
    |
    */
    'variance_thresholds' => [
        'warning_pct'  => 0.5,    // % of litres sold
        'critical_pct' => 2.0,    // % of litres sold
        'warning_abs'  => 200,    // litres absolute
        'critical_abs' => 1000,   // litres absolute
    ],

    /*
    |--------------------------------------------------------------------------
    | SHS / Electrical Discrepancy Tolerance
    |--------------------------------------------------------------------------
    |
    | Maximum allowable difference between (electrical_litres × price) and
    | shs_sold (the pump's own KES odometer). Expressed as a percentage.
    | Exceeding this threshold raises a warning on the reading row.
    |
    */
    'shs_tolerance_pct' => 1.0,

];
