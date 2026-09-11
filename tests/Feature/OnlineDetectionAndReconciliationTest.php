<?php

namespace Tests\Feature;

use App\Actions\Commands\ReconcilePendingCommands;
use App\Actions\Dashboard\DetectOnlineFlips;
use App\Events\CommandStatusChanged;
use App\Events\FleetOnlineChanged;
use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\CommandType;
use App\Models\Device;
use App\Models\DeviceState;
use App\Models\Granularity;
use App\Models\NormalizedEvent;
use App\Models\SwitchState;
use App\Models\Vendor;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnlineDetectionAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_silent_lumina_device_flips_offline_and_broadcasts_the_change(): void
    {
        Event::fake([FleetOnlineChanged::class, CommandStatusChanged::class]);

        $device = Device::factory()->create();
        DeviceState::factory()->for($device, 'device')->create([
            'is_online' => true,
            'last_received_at' => now()->subSeconds(200), // > 180s threshold
        ]);

        $changes = app(DetectOnlineFlips::class)->detect();

        $this->assertCount(1, $changes);
        $this->assertSame($device->id, $changes[0]['device_id']);
        $this->assertFalse($changes[0]['is_online']);
        $this->assertFalse($device->state->refresh()->is_online);
    }

    public function test_recent_lumina_device_stays_online(): void
    {
        $device = Device::factory()->create();
        DeviceState::factory()->for($device, 'device')->create([
            'is_online' => true,
            'last_received_at' => now()->subSeconds(100), // < 180s threshold
        ]);

        $changes = app(DetectOnlineFlips::class)->detect();

        $this->assertSame([], $changes);
        $this->assertTrue($device->state->refresh()->is_online);
    }

    public function test_cp3000_uses_its_own_five_minute_threshold(): void
    {
        $device = Device::factory()->cp3000()->create();
        DeviceState::factory()->for($device, 'device')->create([
            'is_online' => true,
            'last_received_at' => now()->subSeconds(800), // < 900s: still online
        ]);

        $this->assertSame([], app(DetectOnlineFlips::class)->detect());

        DeviceState::query()->where('device_id', $device->id)->update([
            'last_received_at' => now()->subSeconds(1000), // > 900s: offline
        ]);

        $changes = app(DetectOnlineFlips::class)->detect();
        $this->assertCount(1, $changes);
        $this->assertSame($device->external_id, $changes[0]['cabinet_code']); // Lot C fallback code
        $this->assertFalse($device->state->refresh()->is_online);
    }

    public function test_online_flips_are_broadcast_sparsely(): void
    {
        Event::fake([FleetOnlineChanged::class, CommandStatusChanged::class]);

        $device = Device::factory()->create();
        DeviceState::factory()->for($device, 'device')->create([
            'is_online' => true,
            'last_received_at' => now()->subSeconds(500),
        ]);

        app(DetectOnlineFlips::class)->detectAndBroadcast();

        Event::assertDispatched(FleetOnlineChanged::class,
            fn (FleetOnlineChanged $event) => count($event->changes) === 1 && $event->changes[0]['device_id'] === $device->id,
        );
    }

    public function test_matching_lot_c_reading_confirms_a_sent_command(): void
    {
        // Note: the simulator never feeds issued commands back into its
        // physics, so live traffic only produces a match when telemetry
        // already reflects the intent. This test synthesizes the event to
        // exercise the reconciliation logic itself (patch §7): we assert on
        // the command's status transition, not on device_readings fields.

        Event::fake([FleetOnlineChanged::class, CommandStatusChanged::class]);

        $command = Command::factory()
            ->for(Device::factory()->cp3000()->create(['external_id' => '0042']), 'device')
            ->type(CommandType::On)
            ->status(CommandStatus::Sent)
            ->create([
                'sent_at' => now()->subMinute(),
                'reconcile_by' => now()->addMinutes(9),
            ]);

        app(ReconcilePendingCommands::class)->reconcile($this->cp3000Event(switchState: SwitchState::On));

        $this->assertSame(CommandStatus::ConfirmedByTelemetry, $command->refresh()->status);
        $this->assertNotNull($command->confirmed_at);

        Event::assertDispatched(CommandStatusChanged::class);
    }

    public function test_non_matching_reading_leaves_the_command_open_for_reconciliation(): void
    {
        $command = Command::factory()
            ->for(Device::factory()->cp3000()->create(['external_id' => '0042']), 'device')
            ->type(CommandType::Off)
            ->status(CommandStatus::Sent)
            ->create([
                'sent_at' => now()->subMinute(),
                'reconcile_by' => now()->addMinutes(9),
            ]);

        app(ReconcilePendingCommands::class)->reconcile($this->cp3000Event(switchState: SwitchState::On));

        $this->assertSame(CommandStatus::Sent, $command->refresh()->status);
    }

    public function test_lot_c_dim_commands_never_reconcile(): void
    {
        // CP-3000 reports no dim level, so a dim can never be matched:
        // it will honestly end Unconfirmed once the window elapses.
        $command = Command::factory()
            ->for(Device::factory()->cp3000()->create(['external_id' => '0042']), 'device')
            ->type(CommandType::Dim, 70)
            ->status(CommandStatus::Sent)
            ->create([
                'sent_at' => now()->subMinute(),
                'reconcile_by' => now()->addMinutes(9),
            ]);

        app(ReconcilePendingCommands::class)->reconcile($this->cp3000Event(switchState: SwitchState::On));

        $this->assertSame(CommandStatus::Sent, $command->refresh()->status);
    }

    public function test_expired_reconciliation_window_no_longer_confirms(): void
    {
        $command = Command::factory()
            ->for(Device::factory()->cp3000()->create(['external_id' => '0042']), 'device')
            ->type(CommandType::On)
            ->status(CommandStatus::Sent)
            ->create([
                'sent_at' => now()->subMinutes(11),
                'reconcile_by' => now()->subMinute(),
            ]);

        app(ReconcilePendingCommands::class)->reconcile($this->cp3000Event(switchState: SwitchState::On));

        $this->assertSame(CommandStatus::Sent, $command->refresh()->status);
    }

    public function test_lot_a_events_do_not_trigger_reconciliation(): void
    {
        $command = Command::factory()
            ->for(Device::factory()->create(), 'device')
            ->type(CommandType::On)
            ->status(CommandStatus::Sent)
            ->create(['sent_at' => now()->subMinute()]);

        $event = new NormalizedEvent(
            vendor: Vendor::LuminaP2P,
            vendorDeviceId: $command->device->external_id,
            granularity: Granularity::Point,
            receivedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            deviceReportedAt: null,
            powerW: 50.0,
            energyWhCumulative: 1.0,
            switchState: SwitchState::On,
            alarmCodes: [],
            dedupKey: bin2hex(random_bytes(8)),
            rawPayload: ['meas' => ['dim' => 70]],
        );

        app(ReconcilePendingCommands::class)->reconcile($event);

        $this->assertSame(CommandStatus::Sent, $command->refresh()->status);
    }

    private function cp3000Event(SwitchState $switchState): NormalizedEvent
    {
        return new NormalizedEvent(
            vendor: Vendor::Cp3000,
            vendorDeviceId: '0042',
            granularity: Granularity::Line,
            receivedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            deviceReportedAt: null,
            powerW: 5000.0,
            energyWhCumulative: 100.0,
            switchState: $switchState,
            alarmCodes: [],
            dedupKey: bin2hex(random_bytes(8)),
            rawPayload: ['raw' => 'CP3000;0042;...'],
        );
    }
}
