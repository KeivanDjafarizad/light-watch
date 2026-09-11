<?php

namespace App\Actions\Dashboard;

use App\Models\DeviceReading;
use App\Models\Vendor;
use Illuminate\Support\Collection;

/**
 * Last hour of readings for a cabinet, server-side aggregated (PRD §7
 * GET /api/cabinets/{code}): the websocket only appends points going
 * forward, so this is the chart's bootstrap history.
 *
 * - Lot C: the cabinet device's own readings (5-minute cadence).
 * - Lot A: per-minute sum of power across the cabinet's points —
 *   never 38k raw rows to the browser.
 *
 * @phpstan-type SeriesPoint array{t: string, power_w: float}
 */
final class BuildCabinetSeries
{
    /**
     * @param  list<int>  $deviceIds
     * @return list<SeriesPoint>
     */
    public function build(array $deviceIds, string $vendor): array
    {
        if ($deviceIds === []) {
            return [];
        }

        $since = now()->subHour();

        if ($vendor === Vendor::Cp3000->value) {
            return array_values(DeviceReading::query()
                ->whereIn('device_id', $deviceIds)
                ->where('received_at', '>=', $since)
                ->orderBy('received_at')
                ->get(['received_at', 'power_w'])
                ->map(fn (DeviceReading $reading) => [
                    't' => $reading->received_at->toIso8601String(),
                    'power_w' => round((float) ($reading->power_w ?? 0), 2),
                ])
                ->all());
        }

        // Lot A: bucket by minute so the line stays a line even with
        // hundreds of points reporting within the same minute.
        $buckets = DeviceReading::query()
            ->whereIn('device_id', $deviceIds)
            ->where('received_at', '>=', $since)
            ->get(['received_at', 'power_w'])
            ->groupBy(fn (DeviceReading $reading) => $reading->received_at->getTimestamp() - $reading->received_at->getTimestamp() % 60);

        return array_values($buckets
            ->sortKeys()
            ->map(fn (Collection $readings, int $minute) => [
                't' => date('c', $minute),
                'power_w' => round((float) $readings->sum('power_w'), 2),
            ])
            ->all());
    }
}
