<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upcoming reservations preview
    |--------------------------------------------------------------------------
    |
    | Number of future occupying reservations returned on the operational
    | dashboard snapshot. Clients may request fewer via ?upcoming_limit=.
    |
    */

    'upcoming_limit_default' => (int) env('REZERA_DASHBOARD_UPCOMING_LIMIT', 10),

    'upcoming_limit_max' => (int) env('REZERA_DASHBOARD_UPCOMING_LIMIT_MAX', 25),

];
