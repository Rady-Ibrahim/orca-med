<?php

return [

    /*
    |--------------------------------------------------------------------------
    | How long company users can view sensitive pharmacy names after entering
    | the company sensitive password (second factor).
    |--------------------------------------------------------------------------
    */
    'sensitive_unlock_ttl_minutes' => (int) env('ORCA_SENSITIVE_UNLOCK_TTL', 120),

];
