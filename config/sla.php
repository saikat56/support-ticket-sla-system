<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SLA Priority Durations (in Hours)
    |--------------------------------------------------------------------------
    |
    | Defines the maximum resolution time allowed per ticket priority.
    | Changing these values here dynamically adjusts due date calculation
    | without touching any business logic in the application.
    |
    */
    'priorities' => [
        'low' => env('SLA_HOURS_LOW', 48),
        'medium' => env('SLA_HOURS_MEDIUM', 24),
        'high' => env('SLA_HOURS_HIGH', 8),
        'critical' => env('SLA_HOURS_CRITICAL', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Working Days
    |--------------------------------------------------------------------------
    |
    | ISO-8601 day numbers (0 = Sunday, 1 = Monday, ..., 6 = Saturday).
    | Default configuration: Sunday through Thursday (0, 1, 2, 3, 4).
    |
    */
    'business_days' => [0, 1, 2, 3, 4],

    /*
    |--------------------------------------------------------------------------
    | Daily Business Hours (24-hour format: HH:MM)
    |--------------------------------------------------------------------------
    |
    | Default: 9:00 AM to 6:00 PM (9 working hours per day).
    |
    */
    'business_start' => env('SLA_BUSINESS_START', '09:00'),
    'business_end' => env('SLA_BUSINESS_END', '18:00'),

    /*
    |--------------------------------------------------------------------------
    | Holidays (YYYY-MM-DD)
    |--------------------------------------------------------------------------
    |
    | List of dates to be treated as non-working holidays.
    |
    */
    'holidays' => [],

    /*
    |--------------------------------------------------------------------------
    | Approaching SLA Warning Threshold
    |--------------------------------------------------------------------------
    |
    | A ticket is marked as "Approaching SLA Breach" if the remaining
    | business time is less than or equal to this percentage of total SLA time
    | or within this absolute minute threshold.
    |
    */
    'approaching_percentage' => 20, // 20% time left
    'approaching_minutes' => 60,   // or 60 minutes left
];

