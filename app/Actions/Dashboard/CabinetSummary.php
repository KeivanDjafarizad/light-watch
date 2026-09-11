<?php

namespace App\Actions\Dashboard;

/**
 * One row of the cabinet list / one `cabinet.{code}` channel payload
 * (PRD §7/§8/§9.2). Lot C cabinets are the reporting devices themselves;
 * Lot A cabinets only exist once plant:import has populated cabinet_code.
 */
final readonly class CabinetSummary
{
    /**
     * @param  list<int>  $deviceIds  devices reporting under this cabinet
     * @param  list<string>  $deviceExternalIds
     */
    public function __construct(
        public string $code,
        public ?string $name,
        public string $vendor,
        public int $pointsOnline,
        public int $pointsOffline,
        public float $powerW,
        public bool $isOnline,
        public int $activeAlarms,
        public ?string $worstAlarmSeverity,
        public ?float $lat,
        public ?float $lng,
        public array $deviceIds,
        public array $deviceExternalIds,
    ) {}

    /**
     * @phpstan-return array{cabinet_code: string, power_w: float, is_online: bool, points_online: int, points_offline: int, active_alarms: int, generated_at: string}
     */
    public function toSnapshotPayload(string $generatedAt): array
    {
        return [
            'cabinet_code' => $this->code,
            'power_w' => round($this->powerW, 2),
            'is_online' => $this->isOnline,
            'points_online' => $this->pointsOnline,
            'points_offline' => $this->pointsOffline,
            'active_alarms' => $this->activeAlarms,
            'generated_at' => $generatedAt,
        ];
    }

    /** @return array<string, mixed> the `/api/cabinets` row shape */
    public function toListArray(): array
    {
        return [
            'cabinet_code' => $this->code,
            'cabinet_name' => $this->name,
            'vendor' => $this->vendor,
            'points_online' => $this->pointsOnline,
            'points_offline' => $this->pointsOffline,
            'power_w' => round($this->powerW, 2),
            'is_online' => $this->isOnline,
            'active_alarms' => $this->activeAlarms,
            'worst_alarm_severity' => $this->worstAlarmSeverity,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'devices' => $this->deviceIds,
        ];
    }
}
