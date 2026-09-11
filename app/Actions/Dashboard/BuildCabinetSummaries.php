<?php

namespace App\Actions\Dashboard;

use App\Models\Alarm;
use App\Models\AlarmSeverity;
use App\Models\Device;
use App\Models\Granularity;
use App\Models\Vendor;
use Illuminate\Support\Collection;

/**
 * Groups devices into cabinets (PRD §7 GET /api/cabinets and the
 * `cabinet.{code}` channels of PRD §6).
 *
 * Cabinet code derivation: `cabinet_code` when plant:import has run,
 * falling back to `external_id` for Lot C — the CP-3000 reporting device
 * IS the cabinet, so the 11 Lot C cabinets always group, import or not.
 * Lot A points only group once their `cabinet_code` is populated.
 *
 * Point counting follows the same rule as the fleet KPI (PRD §11,
 * option (a) — see NOTES.md).
 */
final class BuildCabinetSummaries
{
    /** @return Collection<int, CabinetSummary> */
    public function build(): Collection
    {
        $openAlarmSeverities = $this->openAlarmSeveritiesByDevice();

        $groups = [];

        foreach (Device::query()->with('state')->get() as $device) {
            $code = $device->cabinetCode();

            if ($code !== null) {
                $groups[$code][] = $device;
            }
        }

        ksort($groups);

        return collect(array_map(
            fn (string $code) => $this->summarize($code, $groups[$code], $openAlarmSeverities),
            array_keys($groups),
        ));
    }

    /**
     * @return array<int, list<AlarmSeverity>> open alarm severities indexed by device id
     */
    private function openAlarmSeveritiesByDevice(): array
    {
        $severities = [];

        $alarms = Alarm::query()->where('status', 'open')->get(['device_id', 'severity']);

        foreach ($alarms as $alarm) {
            $severities[$alarm->device_id][] = $alarm->severity;
        }

        return $severities;
    }

    /**
     * @param  list<Device>  $cabinetDevices
     * @param  array<int, list<AlarmSeverity>>  $openAlarmSeverities
     */
    private function summarize(string $code, array $cabinetDevices, array $openAlarmSeverities): CabinetSummary
    {
        $states = [];

        foreach ($cabinetDevices as $device) {
            if ($device->state !== null) {
                $states[] = $device->state;
            }
        }

        $pointsOnline = count(array_filter($states, fn ($state) => $state->is_online));
        $pointsOffline = count($states) - $pointsOnline;

        // Instantaneous power: online devices only, same rule as the fleet KPI.
        $powerW = 0.0;

        foreach ($states as $state) {
            if ($state->is_online) {
                $powerW += (float) ($state->last_power_w ?? 0);
            }
        }

        $alarmSeverities = [];

        foreach ($cabinetDevices as $device) {
            foreach ($openAlarmSeverities[$device->id] ?? [] as $severity) {
                $alarmSeverities[] = $severity;
            }
        }

        // A cabinet group is either one Lot C reporting device or a set of
        // Lot A points sharing a physical cabinet; the two never mix.
        $vendor = in_array(Granularity::Line, array_map(fn (Device $device) => $device->granularity, $cabinetDevices), true)
            ? Vendor::Cp3000
            : Vendor::LuminaP2P;

        $lat = $this->averageCoordinate(array_map(fn (Device $device) => $device->lat, $cabinetDevices));
        $lng = $this->averageCoordinate(array_map(fn (Device $device) => $device->lng, $cabinetDevices));

        $name = null;

        foreach ($cabinetDevices as $device) {
            $name ??= $device->cabinet_name;
        }

        return new CabinetSummary(
            code: $code,
            name: $name,
            vendor: $vendor->value,
            pointsOnline: $pointsOnline,
            pointsOffline: $pointsOffline,
            powerW: $powerW,
            isOnline: $pointsOnline > 0,
            activeAlarms: count($alarmSeverities),
            worstAlarmSeverity: AlarmSeverity::worstOf($alarmSeverities)?->value,
            lat: $lat,
            lng: $lng,
            deviceIds: array_map(fn (Device $device) => $device->id, $cabinetDevices),
            deviceExternalIds: array_map(fn (Device $device) => $device->external_id, $cabinetDevices),
        );
    }

    /** @param list<float|null> $values */
    private function averageCoordinate(array $values): ?float
    {
        $present = array_values(array_filter($values, fn ($value) => $value !== null));

        if ($present === []) {
            return null;
        }

        return round(array_sum($present) / count($present), 7);
    }
}
