<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor-specific constants (PRD part 2, patch §4)
    |--------------------------------------------------------------------------
    |
    | Values the vendors document themselves, as opposed to the dashboard
    | tunables in config/dashboard.php.
    |
    */

    'lumina' => [
        // Vendor-recommended ack timeout: no ack within 30s of Sent -> Failed.
        'ack_timeout' => env('LUMINA_ACK_TIMEOUT_S', 30),
    ],

];
