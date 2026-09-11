<?php

namespace Tests\Feature;

use App\Actions\Ingestion\TelemetryNormalizer;
use App\Models\Alarm;
use App\Models\AlarmCode;
use App\Models\AlarmSeverity;
use App\Models\Device;
use App\Models\Granularity;
use App\Models\NormalizedEvent;
use App\Models\SwitchState;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryAlarmLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_with_alarm_codes_opens_one_alarm_per_code(): void
    {
        $this->applyLuminaEvent(alarmCodes: [AlarmCode::LampFault, AlarmCode::Unknown]);

        $this->assertSame(2, Alarm::query()->count());
        $this->assertSame(2, Alarm::query()->where('status', 'open')->count());

        $lampFault = Alarm::query()->where('code', AlarmCode::LampFault->value)->first();
        $this->assertNotNull($lampFault);
        $this->assertSame(AlarmSeverity::Warning, $lampFault->severity);
        $this->assertNotNull($lampFault->opened_at);
        $this->assertNull($lampFault->closed_at);
    }

    public function test_reading_without_a_code_closes_the_open_alarm_for_that_code(): void
    {
        $this->applyLuminaEvent(alarmCodes: [AlarmCode::LampFault, AlarmCode::DoorOpen]);
        $this->applyLuminaEvent(alarmCodes: [AlarmCode::DoorOpen]);

        $this->assertSame(2, Alarm::query()->count());
        $this->assertSame(1, Alarm::query()->where('status', 'open')->count());

        $lampFault = Alarm::query()->where('code', AlarmCode::LampFault->value)->first();
        $this->assertSame('closed', $lampFault->status);
        $this->assertNotNull($lampFault->closed_at);

        $doorOpen = Alarm::query()->where('code', AlarmCode::DoorOpen->value)->first();
        $this->assertSame('open', $doorOpen->status);
    }

    public function test_a_recurring_alarm_opens_a_new_row_after_closing(): void
    {
        $this->applyLuminaEvent(alarmCodes: [AlarmCode::PhaseLoss]);
        $this->applyLuminaEvent(alarmCodes: []);
        $this->applyLuminaEvent(alarmCodes: [AlarmCode::PhaseLoss]);

        $this->assertSame(2, Alarm::query()->where('code', AlarmCode::PhaseLoss->value)->count());
        $this->assertSame(1, Alarm::query()->where('status', 'open')->count());
    }

    public function test_alarms_are_scoped_per_device(): void
    {
        $this->applyLuminaEvent(node: 'LUM-000001', alarmCodes: [AlarmCode::LampFault]);
        $this->applyLuminaEvent(node: 'LUM-000002', alarmCodes: []);

        // the alarm-free reading of LUM-000002 must not close LUM-000001's alarm
        $this->assertSame(1, Alarm::query()->where('status', 'open')->count());
    }

    public function test_device_state_tracks_last_known_power_and_switch_state(): void
    {
        $device = $this->applyLuminaEvent(node: 'LUM-000001', powerW: 123.5, switchState: SwitchState::On);
        $this->applyLuminaEvent(node: 'LUM-000001', powerW: 0.0, switchState: SwitchState::Off);

        $device->refresh();
        $state = $device->state;

        $this->assertNotNull($state);
        $this->assertEquals(0.0, $state->last_power_w);
        $this->assertSame(SwitchState::Off, $state->last_switch_state);
        $this->assertNotNull($state->last_seen_at);
    }

    private function applyLuminaEvent(
        string $node = 'LUM-000001',
        array $alarmCodes = [],
        ?float $powerW = 55.5,
        SwitchState $switchState = SwitchState::On,
    ) {
        $event = new NormalizedEvent(
            vendor: Vendor::LuminaP2P,
            vendorDeviceId: $node,
            granularity: Granularity::Point,
            receivedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            deviceReportedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            powerW: $powerW,
            energyWhCumulative: 1000.0,
            switchState: $switchState,
            alarmCodes: $alarmCodes,
            dedupKey: bin2hex(random_bytes(16)),
            rawPayload: [],
        );

        app(TelemetryNormalizer::class)->apply($event);

        return Device::query()
            ->where('vendor', Vendor::LuminaP2P->value)
            ->where('external_id', $node)
            ->firstOrFail();
    }
}
