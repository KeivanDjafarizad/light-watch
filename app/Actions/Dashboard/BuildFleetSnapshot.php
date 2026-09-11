<?php

namespace App\Actions\Dashboard;

use Illuminate\Support\Facades\DB;

/**
 * Aggregated fleet KPIs (PRD §8, `fleet` channel `snapshot` event).
 *
 * "Points" are telemetry devices: Lot A contributes one point per device,
 * Lot C one unit per cabinet (the honest option (a) of PRD §11 — the
 * physical point count per cabinet only exists in the optional
 * plant.csv import; see NOTES.md). Total instantaneous power sums
 * `last_power_w` over online devices only: an offline device's last
 * reading may be hours old and is not "instantaneous" anything.
 */
final class BuildFleetSnapshot
{
    /** @return array{points_online: int, points_offline: int, points_total: int, power_w_total: float, active_alarms: int, generated_at: string} */
    public function build(): array
    {
        $totals = DB::table('device_states as ds')
            ->join('devices as d', 'd.id', '=', 'ds.device_id')
            ->selectRaw('COUNT(*) as points_total')
            ->selectRaw('COALESCE(SUM(ds.is_online), 0) as points_online')
            ->selectRaw('COALESCE(SUM(CASE WHEN ds.is_online = 1 THEN COALESCE(ds.last_power_w, 0) ELSE 0 END), 0) as power_w_total')
            ->first();

        $activeAlarms = DB::table('alarms')->where('status', 'open')->count();

        $pointsTotal = (int) ($totals->points_total ?? 0);
        $pointsOnline = (int) ($totals->points_online ?? 0);

        return [
            'points_online' => $pointsOnline,
            'points_offline' => $pointsTotal - $pointsOnline,
            'points_total' => $pointsTotal,
            'power_w_total' => round((float) ($totals->power_w_total ?? 0), 2),
            'active_alarms' => $activeAlarms,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
