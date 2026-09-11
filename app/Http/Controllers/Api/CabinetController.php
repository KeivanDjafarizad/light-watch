<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\BuildCabinetSeries;
use App\Actions\Dashboard\BuildCabinetSummaries;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommandResource;
use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class CabinetController extends Controller
{
    /**
     * One row per cabinet (PRD §7/§9.2): Lot C cabinets always show up
     * (the reporting device is the cabinet); Lot A cabinets only once
     * plant:import has populated their cabinet_code. ~15 rows, no
     * pagination needed.
     *
     * @return list<array<string, mixed>>
     */
    public function index(BuildCabinetSummaries $builder): array
    {
        return array_values($builder->build()
            ->map(fn ($cabinet) => $cabinet->toListArray())
            ->all());
    }

    /**
     * Cabinet detail: aggregates, the device list for the command picker,
     * the last hour of readings (chart bootstrap) and any outstanding
     * commands (optimistic-UI reload recovery, PRD §7/§9.4).
     */
    public function show(string $code, BuildCabinetSummaries $summaries, BuildCabinetSeries $series): JsonResponse
    {
        $summary = $summaries->build()->first(fn ($cabinet) => $cabinet->code === $code);

        if ($summary === null) {
            abort(404, "Unknown cabinet [{$code}].");
        }

        $devices = Device::query()
            ->whereIn('id', $summary->deviceIds)
            ->with('state')
            ->orderBy('external_id')
            ->get();

        $outstanding = Command::query()
            ->whereIn('device_id', $summary->deviceIds)
            ->whereIn('status', array_map(fn ($status) => $status->value, CommandStatus::active()))
            ->orderByDesc('issued_at')
            ->get();

        return response()->json([
            'cabinet' => $summary->toListArray(),
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'external_id' => $device->external_id,
                'label' => $device->label,
                'vendor' => $device->vendor->value,
                'is_online' => (bool) $device->state?->is_online,
                'switch_state' => $device->state?->last_switch_state?->value,
                'power_w' => $device->state?->last_power_w !== null ? round((float) $device->state->last_power_w, 2) : null,
            ])->all(),
            'series' => $series->build($summary->deviceIds, $summary->vendor),
            'outstanding_commands' => CommandResource::collection($outstanding),
        ]);
    }
}
