<?php

namespace App\Actions\Ingestion;

use App\Actions\Ingestion\VendorAdapter;
use App\Models\AlarmCode;
use App\Models\Granularity;
use App\Models\NormalizedEvent;
use App\Models\SwitchState;
use App\Models\Vendor;
use DateTimeImmutable;
use DateTimeZone;

final class Cp3000Adapter implements VendorAdapter
{
    private const TZ = 'Europe/Rome';

    public function supports(string $topic): bool
    {
        return str_starts_with($topic, 'cp3000/') && str_ends_with($topic, '/data');
    }

    public function normalize(string $topic, string $rawPayload, DateTimeImmutable $receivedAt): array
    {
        $parts = explode(';', trim($rawPayload));
        [, $cabinetCode, $localTs] = $parts;

        $totalW = 0.0;
        $energyKwh = 0.0;
        $alarms = [];

        foreach (array_slice($parts, 3) as $field) {
            if (str_starts_with($field, 'L')) {
                [, $vals] = explode(':', $field, 2);
                [, , $w] = array_map('floatval', explode(',', $vals));
                $totalW += $w;
            } elseif (str_starts_with($field, 'EN:')) {
                $energyKwh = (float) substr($field, 3);
            } elseif (str_starts_with($field, 'AL:')) {
                $codes = array_filter(explode(',', substr($field, 3)));
                $alarms = array_map(fn (string $c) => $this->mapAlarm($c), $codes);
            }
        }

        $deviceReportedAt = DateTimeImmutable::createFromFormat(
            'YmdHis', $localTs, new DateTimeZone(self::TZ)
        )?->setTimezone(new DateTimeZone('UTC')) ?: null;

        return [new NormalizedEvent(
                    vendor: Vendor::Cp3000,
                    vendorDeviceId: $cabinetCode,
                    granularity: Granularity::Line,
                    receivedAt: $receivedAt,
                    deviceReportedAt: $deviceReportedAt,
                    powerW: $totalW,
                    energyWhCumulative: $energyKwh * 1000,
                    switchState: $totalW > 0 ? SwitchState::On : SwitchState::Off,
                    alarmCodes: $alarms,
                    dedupKey: hash('xxh128', $topic . $rawPayload),
                    rawPayload: ['raw' => $rawPayload],
                )];
    }

    private function mapAlarm(string $vendorCode): AlarmCode
    {
        return match (true) {
            preg_match('/^PF\d$/', $vendorCode) === 1 => AlarmCode::PhaseLoss,
            $vendorCode === 'DOOR' => AlarmCode::DoorOpen,
            default => AlarmCode::Unknown,
        };
    }
}
