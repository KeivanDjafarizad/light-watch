<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlantImportTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = storage_path('app/test-plant.csv');

        File::put($this->csv, implode("\n", [
            'lot,cabinet_code,cabinet_name,line,point_code,device_id,rated_power_w,lamp_type,lat,lng,pole_id',
            'A,Q-A01,Via Roma Nord,L1,PL-000001,LUM-000001,72,LED,45.40000,9.13700,P-000001',
            'A,Q-A01,Via Roma Nord,L2,PL-000002,LUM-000002,48,LED,45.40100,9.13800,P-000002',
            'A,Q-A02,Via Sud,L1,PL-000003,LUM-000003,56,LED,45.41000,9.14000,P-000003',
            'C,0042,Piazza Centro,L1,PL-000004,,100,SAP,45.42000,9.15000,P-000004',
            'C,0042,Piazza Centro,L2,PL-000005,,150,SAP,45.42200,9.15200,P-000005',
            'C,0043,Via Est,L1,PL-000006,,250,MH,45.43000,9.16000,P-000006',
            'B,Q-B01,Via B,L1,PL-000007,NC-1007,56,LED,45.44000,9.17000,P-000007',
        ])."\n");
    }

    protected function tearDown(): void
    {
        File::delete($this->csv);

        parent::tearDown();
    }

    public function test_imports_lot_a_points_and_lot_c_cabinets(): void
    {
        $this->artisan('plant:import', ['path' => [$this->csv]])->assertSuccessful();

        // Lot A: one device per point, own coordinates, cabinet label.
        $this->assertSame(3, Device::query()->where('vendor', 'lumina_p2p')->count());

        $point = Device::query()->where('external_id', 'LUM-000001')->firstOrFail();
        $this->assertSame('point', $point->granularity->value);
        $this->assertEquals(45.4, $point->lat);
        $this->assertEquals(9.137, $point->lng);
        $this->assertSame('Q-A01', $point->cabinet_code);
        $this->assertSame('Via Roma Nord', $point->cabinet_name);

        // Lot C: one device per cabinet, averaged representative coordinate.
        $this->assertSame(2, Device::query()->where('vendor', 'cp3000')->count());

        $cabinet = Device::query()->where('external_id', '0042')->firstOrFail();
        $this->assertSame('line', $cabinet->granularity->value);
        $this->assertEquals(45.421, $cabinet->lat);
        $this->assertEquals(9.151, $cabinet->lng);
        $this->assertSame('Piazza Centro', $cabinet->cabinet_name);

        // Lot B: documented but not live in ingestion — no device created.
        $this->assertDatabaseMissing('devices', ['external_id' => 'NC-1007']);
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('plant:import', ['path' => [$this->csv]])->assertSuccessful();
        $this->artisan('plant:import', ['path' => [$this->csv]])->assertSuccessful();

        $this->assertSame(5, Device::query()->count());
        $this->assertSame(3, Device::query()->where('vendor', 'lumina_p2p')->count());
        $this->assertSame(2, Device::query()->where('vendor', 'cp3000')->count());
    }

    public function test_import_preserves_existing_device_rows_updating_only_metadata(): void
    {
        $device = Device::factory()->create([
            'vendor' => 'lumina_p2p',
            'external_id' => 'LUM-000001',
            'granularity' => 'point',
        ]);

        $this->artisan('plant:import', ['path' => [$this->csv]])->assertSuccessful();

        $device->refresh();
        $this->assertEquals(45.4, $device->lat);
        $this->assertSame('Q-A01', $device->cabinet_code);
        $this->assertTrue($device->is($device->refresh()));
    }

    public function test_fails_cleanly_on_a_missing_file(): void
    {
        $this->artisan('plant:import', ['path' => ['nowhere.csv']])
            ->expectsOutputToContain('File not found')
            ->assertFailed();
    }
}
