<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Realtime dashboard (Part 2)
    |--------------------------------------------------------------------------
    |
    | Tunables for the supervised `dashboard:broadcast` loop, the online /
    | offline detection thresholds and the command lifecycle timeouts.
    |
    */

    'broadcast_interval_seconds' => env('DASHBOARD_BROADCAST_INTERVAL', 5),

    /*
     | Silence threshold per vendor = 3x the expected reporting interval
     | (PRD §6): Lumina P2P reports ~every 60s -> 180s, CP-3000 every
     | 5 minutes -> 900s.
     */
    'online_thresholds' => [
        'lumina_p2p' => env('DASHBOARD_LUMINA_OFFLINE_S', 180),
        'cp3000' => env('DASHBOARD_CP3000_OFFLINE_S', 900),
    ],

    'commands' => [
        // Lot C: reconciliation window = ~2x the expected telemetry interval.
        'reconcile_window_seconds' => env('COMMAND_RECONCILE_WINDOW_S', 600),

        // ttl_s advertised in outgoing Lumina P2P command payloads.
        'lumina_ttl_s' => env('COMMAND_LUMINA_TTL_S', 30),
    ],
];
