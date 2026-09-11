<?php

namespace Tests\Feature;

use App\Models\Alarm;
use App\Models\AlarmCode;
use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\CommandType;
use App\Models\Device;
use App\Models\DeviceReading;
use App\Models\DeviceState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/fleet/snapshot')->assertUnauthorized();
        $this->getJson('/api/cabinets')->assertUnauthorized();
        $this->getJson('/api/cabinets/0042')->assertUnauthorized();
        $this->getJson('/api/alarms')->assertUnauthorized();
        $this->postJson('/api/commands', [])->assertUnauthorized();
    }

    public function test_fleet_snapshot_reports_counts_power_and_alarms(): void
    {
        $this->actingAsUser();

        $online = Device::factory()->has(DeviceState::factory()->state(['last_power_w' => 100.5]), 'state')->create();
        $offline = Device::factory()->has(DeviceState::factory()->offline()->state(['last_power_w' => 40.0]), 'state')->create();
        Alarm::factory()->code(AlarmCode::PhaseLoss)->create(['device_id' => $online->id]);
        Alarm::factory()->closed()->create(['device_id' => $offline->id]);

        $this->getJson('/api/fleet/snapshot')
            ->assertOk()
            ->assertJson([
                'points_online' => 1,
                'points_offline' => 1,
                'points_total' => 2,
                'power_w_total' => 100.5, // offline device's stale power excluded
                'active_alarms' => 1,
            ])
            ->assertJsonStructure(['generated_at']);
    }

    public function test_cabinets_always_include_lot_c_and_group_lot_a_by_cabinet_code(): void
    {
        $this->actingAsUser();

        // Lot C device without any import: groups via external_id fallback.
        $lotC = Device::factory()->cp3000()->create(['external_id' => '0042']);
        DeviceState::factory()->for($lotC, 'device')->create(['last_power_w' => 5000.0]);

        // Lot A points only group once cabinet_code is populated.
        $lotA = Device::factory()->count(2)->create(['cabinet_code' => 'Q-A01', 'cabinet_name' => 'Via Roma Nord']);
        $lotA->each(fn (Device $device) => DeviceState::factory()->for($device, 'device')->create(['last_power_w' => 60.0]));

        // Un-grouped Lot A point (import never ran for it): absent from the list.
        Device::factory()->has(DeviceState::factory(), 'state')->create();

        $response = $this->getJson('/api/cabinets')->assertOk()->json();

        $this->assertCount(2, $response);

        [$lotCRow, $lotARow] = $response; // sorted: '0042' < 'Q-A01'

        $this->assertSame('Q-A01', $lotARow['cabinet_code']);
        $this->assertSame('Via Roma Nord', $lotARow['cabinet_name']);
        $this->assertSame('lumina_p2p', $lotARow['vendor']);
        $this->assertSame(2, $lotARow['points_online']);
        $this->assertSame(0, $lotARow['points_offline']);
        $this->assertEquals(120.0, $lotARow['power_w']);

        $this->assertSame('0042', $lotCRow['cabinet_code']);
        $this->assertSame('cp3000', $lotCRow['vendor']);
        $this->assertSame(1, $lotCRow['points_online']);
        $this->assertEquals(5000.0, $lotCRow['power_w']);
    }

    public function test_cabinet_status_reflects_worst_case_severity_and_offline_points(): void
    {
        $this->actingAsUser();

        $critical = Device::factory()->cp3000()->create(['external_id' => '0042']);
        DeviceState::factory()->for($critical, 'device')->create();
        Alarm::factory()->code(AlarmCode::PhaseLoss)->create(['device_id' => $critical->id]);

        $offline = Device::factory()->cp3000()->create(['external_id' => '0043']);
        DeviceState::factory()->for($offline, 'device')->offline()->create();
        Alarm::factory()->code(AlarmCode::DoorOpen)->create(['device_id' => $offline->id]);

        $clean = Device::factory()->cp3000()->create(['external_id' => '0044']);
        DeviceState::factory()->for($clean, 'device')->create();

        $response = $this->getJson('/api/cabinets')->assertOk()->json();

        $byCode = collect($response)->keyBy('cabinet_code');

        $this->assertSame('critical', $byCode['0042']['worst_alarm_severity']);
        $this->assertSame('info', $byCode['0043']['worst_alarm_severity']);
        $this->assertSame(1, $byCode['0043']['points_offline']);
        $this->assertNull($byCode['0044']['worst_alarm_severity']);
    }

    public function test_cabinet_detail_returns_series_devices_and_outstanding_commands(): void
    {
        $this->actingAsUser();

        $cabinet = Device::factory()->cp3000()->create(['external_id' => '0042']);
        DeviceState::factory()->for($cabinet, 'device')->create(['last_power_w' => 4321.0]);
        DeviceReading::factory()->for($cabinet, 'device')->create(['received_at' => now()->subMinutes(5), 'power_w' => 4321.0]);
        DeviceReading::factory()->for($cabinet, 'device')->create(['received_at' => now()->subMinutes(2), 'power_w' => 4400.0]);

        $command = Command::factory()->for($cabinet, 'device')->type(CommandType::On)->status(CommandStatus::Sent)->create();

        $response = $this->getJson('/api/cabinets/0042')->assertOk()->json();

        $this->assertSame('0042', $response['cabinet']['cabinet_code']);
        $this->assertCount(1, $response['devices']);
        $this->assertSame('cp3000', $response['devices'][0]['vendor']);

        $this->assertCount(2, $response['series']);
        // series[0] < series[1]: assertLessThan(expected, actual) asserts actual < expected
        $this->assertLessThan(
            $response['series'][1]['t'],
            $response['series'][0]['t'],
        );

        $this->assertCount(1, $response['outstanding_commands']);
        $this->assertSame($command->id, $response['outstanding_commands'][0]['id']);
        $this->assertSame('sent', $response['outstanding_commands'][0]['status']);
    }

    public function test_cabinet_detail_with_no_readings_degrades_to_an_empty_series(): void
    {
        $this->actingAsUser();

        $cabinet = Device::factory()->cp3000()->create(['external_id' => '0042']);
        DeviceState::factory()->for($cabinet, 'device')->create();

        $this->getJson('/api/cabinets/0042')
            ->assertOk()
            ->assertJsonPath('series', []);
    }

    public function test_unknown_cabinet_returns_404(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/cabinets/nope')->assertNotFound();
    }

    public function test_alarms_endpoint_filters_by_status(): void
    {
        $this->actingAsUser();

        $device = Device::factory()->create();
        Alarm::factory()->count(2)->create(['device_id' => $device->id]);
        Alarm::factory()->closed()->create(['device_id' => $device->id]);

        $this->getJson('/api/alarms')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.device.external_id', $device->external_id);

        $this->getJson('/api/alarms?status=closed')->assertOk()->assertJsonCount(1);
    }
}
