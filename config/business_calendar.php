<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calendar range limits
    |--------------------------------------------------------------------------
    */

    'max_range_days' => (int) env('REZERA_CALENDAR_MAX_RANGE_DAYS', 31),

    'default_resource_schedule_days' => (int) env('REZERA_CALENDAR_DEFAULT_RESOURCE_DAYS', 7),

    'week_starts_on' => (int) env('REZERA_CALENDAR_WEEK_STARTS_ON', 1), // ISO: Monday = 1

];
