<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\BuildFleetSnapshot;
use App\Http\Controllers\Controller;

class FleetSnapshotController extends Controller
{
    /**
     * Current KPI state — the REST bootstrap of the `fleet` channel
     * snapshot (PRD §7/§9.1): the page renders before the first
     * websocket event arrives.
     *
     * @return array<string, mixed>
     */
    public function __invoke(BuildFleetSnapshot $builder): array
    {
        return $builder->build();
    }
}
