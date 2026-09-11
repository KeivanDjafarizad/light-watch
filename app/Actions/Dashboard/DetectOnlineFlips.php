<?php

namespace App\Actions\Dashboard;

use App\Events\FleetOnlineChanged;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Online/offline detection (PRD §6): flips `device_states.is_online`
 * based on a per-vendor silence threshold = 3x the expected reporting
 * interval (Lumina P2P ~60s -> 180s, CP-3000 5min -> 900s) and
 * broadcasts ONLY the changed devices — a sparse, meaningful stream
 * instead of a per-tick heartbeat firehose.
 */
final class DetectOnlineFlips
{
    /**
     * @return list<array{device_id: int, vendor: string, cabinet_code: ?string, is_online: bool}>
     */
    public function detect(): array
    {
        $thresholds = config('dashboard.online_thresholds');

        $cutoffs = [
            Vendor::Cp3000->value => now()->subSeconds((int) $thresholds['cp3000']),
            Vendor::LuminaP2P->value => now()->subSeconds((int) $thresholds['lumina_p2p']),
        ];

        $rows = DB::table('device_states as ds')
            ->join('devices as d', 'd.id', '=', 'ds.device_id')
            ->selectRaw('ds.device_id, d.vendor, d.external_id, d.cabinet_code, ds.is_online')
            ->selectRaw(
                '(ds.last_received_at IS NOT NULL AND ds.last_received_at >= CASE d.vendor WHEN ? THEN ? ELSE ? END) as expected',
                [Vendor::Cp3000->value, $cutoffs[Vendor::Cp3000->value], $cutoffs[Vendor::LuminaP2P->value]],
            )
            ->get();

        $changes = [];
        $flippedIds = ['on' => [], 'off' => []];

        foreach ($rows as $row) {
            $expected = (bool) $row->expected;

            if ($expected === (bool) $row->is_online) {
                continue;
            }

            $flippedIds[$expected ? 'on' : 'off'][] = $row->device_id;

            $changes[] = [
                'device_id' => $row->device_id,
                'vendor' => $row->vendor,
                'cabinet_code' => $row->cabinet_code
                    ?? ($row->vendor === Vendor::Cp3000->value ? $row->external_id : null),
                'is_online' => $expected,
            ];
        }

        if ($flippedIds['on'] !== []) {
            DB::table('device_states')->whereIn('device_id', $flippedIds['on'])->update(['is_online' => true]);
        }

        if ($flippedIds['off'] !== []) {
            DB::table('device_states')->whereIn('device_id', $flippedIds['off'])->update(['is_online' => false]);
        }

        return $changes;
    }

    /** Runs detection and broadcasts the change set when non-empty. */
    public function detectAndBroadcast(): void
    {
        $changes = $this->detect();

        if ($changes !== []) {
            FleetOnlineChanged::dispatch($changes);
        }
    }
}
