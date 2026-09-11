<?php

namespace App\Actions\Ingestion;

use App\Models\AlarmCode;
use App\Models\Granularity;
use App\Models\NormalizedEvent;
use App\Models\SwitchState;
use App\Models\Vendor;
use DateTimeImmutable;

final class LuminaP2PAdapter implements VendorAdapter
{
    public function supports(string $topic): bool
    {
        return str_starts_with($topic, 'lumina/v2/') && str_ends_with($topic, '/telemetry');
    }

    public function normalize(string $topic, string $rawPayload, DateTimeImmutable $receivedAt): array
    {
        $data = json_decode($rawPayload, true, flags: JSON_THROW_ON_ERROR);

        return [new NormalizedEvent(
            vendor: Vendor::LuminaP2P,
            vendorDeviceId: $data['node'],
            granularity: Granularity::Point,
            receivedAt: $receivedAt,
            deviceReportedAt: new DateTimeImmutable($data['ts']),
            powerW: (float) $data['meas']['p'],
            energyWhCumulative: (float) $data['meas']['e_wh'],
            switchState: $this->mapSwitchState($data['st']['relay']),
            alarmCodes: array_map(fn (string $e) => $this->mapAlarm($e), $data['st']['err']),
            dedupKey: hash('xxh128', $topic . $rawPayload),
            rawPayload: $data,
        )];
    }

    private function mapSwitchState(string $relay): SwitchState
    {
        return match (strtoupper($relay)) {
            'ON' => SwitchState::On,
            'OFF' => SwitchState::Off,
            default => SwitchState::Unknown,
        };
    }

    private function mapAlarm(string $vendorCode): AlarmCode
    {
        return match ($vendorCode) {
            'OVERCURRENT' => AlarmCode::OverCurrent,
            'OVERVOLT' => AlarmCode::OverVoltage,
            'UNDERVOLT' => AlarmCode::UnderVoltage,
            'OVERTEMP' => AlarmCode::OverTemperature,
            'DRIVER_FAULT' => AlarmCode::LampFault,
            'PF_LOW' => AlarmCode::PowerFactorLow,
            default => AlarmCode::Unknown,
        };
    }
}
