<?php

namespace Tests\Unit;

use App\Actions\Ingestion\Cp3000Adapter;
use App\Actions\Ingestion\LuminaP2PAdapter;
use App\Models\AlarmCode;
use App\Models\AlarmSeverity;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VendorAlarmMappingTest extends TestCase
{
    public function test_lumina_maps_all_documented_error_codes(): void
    {
        $cases = [
            'OVERCURRENT' => AlarmCode::OverCurrent,
            'OVERVOLT' => AlarmCode::OverVoltage,
            'UNDERVOLT' => AlarmCode::UnderVoltage,
            'OVERTEMP' => AlarmCode::OverTemperature,
            'DRIVER_FAULT' => AlarmCode::LampFault,
            'PF_LOW' => AlarmCode::PowerFactorLow,
            'SOMETHING_ELSE' => AlarmCode::Unknown,
        ];

        foreach ($cases as $vendorCode => $expected) {
            $event = (new LuminaP2PAdapter)->normalize(
                'lumina/v2/sanverano/LUM-000001/telemetry',
                $this->luminaPayload([$vendorCode]),
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            )[0];

            $this->assertSame(
                [$expected],
                $event->alarmCodes,
                "vendor code [{$vendorCode}] should map to {$expected->name}",
            );
        }
    }

    public function test_cp3000_maps_all_documented_alarm_codes(): void
    {
        $cases = [
            'PF1' => AlarmCode::PhaseLoss,
            'PF2' => AlarmCode::PhaseLoss,
            'PF3' => AlarmCode::PhaseLoss,
            'PF4' => AlarmCode::Unknown, // only PF1..PF3 are documented phase losses
            'DOOR' => AlarmCode::DoorOpen,
            'OC' => AlarmCode::OverCurrent,
            'UV' => AlarmCode::UnderVoltage,
            'MAN' => AlarmCode::ManualOverride,
            'CB' => AlarmCode::BreakerTripped,
            'XX' => AlarmCode::Unknown,
        ];

        foreach ($cases as $vendorCode => $expected) {
            $event = $this->cp3000AlarmEvent($vendorCode);

            $this->assertSame(
                [$expected],
                $event->alarmCodes,
                "vendor code [{$vendorCode}] should map to {$expected->name}",
            );
        }
    }

    public function test_cp3000_digital_input_bit_2_synthesizes_manual_override(): void
    {
        // DI:4 (bit 2 set) before an empty AL: — the synthesized code must
        // survive the AL: assignment regardless of field order.
        $event = (new Cp3000Adapter)->normalize(
            'cp3000/0042/data',
            'CP3000;0042;20260911160000;L1:230.0,10.0,1000.0;EN:5;DI:4;AL:',
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        )[0];

        $this->assertSame([AlarmCode::ManualOverride], $event->alarmCodes);
    }

    public function test_cp3000_manual_override_is_not_duplicated_when_al_reports_it(): void
    {
        $event = (new Cp3000Adapter)->normalize(
            'cp3000/0042/data',
            'CP3000;0042;20260911160000;L1:230.0,10.0,1000.0;EN:5;DI:4;AL:MAN',
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        )[0];

        $this->assertSame([AlarmCode::ManualOverride], $event->alarmCodes);
    }

    public function test_cp3000_digital_input_without_bit_2_adds_nothing(): void
    {
        // DI:1 (bit 0 — door) is reported via AL: by the vendor, not synthesized.
        $event = (new Cp3000Adapter)->normalize(
            'cp3000/0042/data',
            'CP3000;0042;20260911160000;L1:230.0,10.0,1000.0;EN:5;DI:1;AL:',
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        )[0];

        $this->assertSame([], $event->alarmCodes);
    }

    #[DataProvider('severityProvider')]
    public function test_default_severity_follows_the_appendix_mapping(AlarmCode $code, AlarmSeverity $expected): void
    {
        $this->assertSame($expected, $code->defaultSeverity(), $code->name);
    }

    public static function severityProvider(): array
    {
        return [
            'phase loss' => [AlarmCode::PhaseLoss, AlarmSeverity::Critical],
            'breaker tripped' => [AlarmCode::BreakerTripped, AlarmSeverity::Critical],
            'over current' => [AlarmCode::OverCurrent, AlarmSeverity::Warning],
            'over voltage' => [AlarmCode::OverVoltage, AlarmSeverity::Warning],
            'under voltage' => [AlarmCode::UnderVoltage, AlarmSeverity::Warning],
            'over temperature' => [AlarmCode::OverTemperature, AlarmSeverity::Warning],
            'lamp fault' => [AlarmCode::LampFault, AlarmSeverity::Warning],
            'unknown' => [AlarmCode::Unknown, AlarmSeverity::Warning],
            'door open' => [AlarmCode::DoorOpen, AlarmSeverity::Info],
            'manual override' => [AlarmCode::ManualOverride, AlarmSeverity::Info],
            'power factor low' => [AlarmCode::PowerFactorLow, AlarmSeverity::Info],
        ];
    }

    /** @param list<string> $errors */
    private function luminaPayload(array $errors): string
    {
        return json_encode([
            'ts' => '2026-09-11T16:00:00Z',
            'node' => 'LUM-000001',
            'seq' => 1,
            'meas' => ['v' => 230, 'i' => 1, 'p' => 50, 'pf' => 0.96, 'e_wh' => 100, 'dim' => 70, 't_int' => 30],
            'st' => ['relay' => 'ON', 'lamp' => 'OK', 'err' => $errors],
        ], JSON_THROW_ON_ERROR);
    }

    private function cp3000AlarmEvent(string $vendorCode)
    {
        return (new Cp3000Adapter)->normalize(
            'cp3000/0042/data',
            'CP3000;0042;20260911160000;L1:230.0,10.0,1000.0;EN:5;DI:0;AL:'.$vendorCode,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        )[0];
    }
}
