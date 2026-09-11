<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Granularity;
use App\Models\Vendor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('plant:import {path* : Path(s) to the plant CSV (e.g. assets/plant.csv)}')]
#[Description('Upsert lat/lng and cabinet metadata onto devices from the plant CSV (idempotent, optional)')]
class PlantImport extends Command
{
    private const CHUNK = 500;

    /**
     * Execute the console command.
     *
     * Optional by design (PRD §4/§12): without this import every device
     * simply has null cabinet/coordinate metadata — the dashboard still
     * works (Lot C cabinets always group via external_id, the map shows
     * its empty state). With it, Lot A points gain their coordinates and
     * cabinet grouping, and Lot C cabinets get a representative
     * coordinate (average of the physical points under that cabinet).
     */
    public function handle(): int
    {
        $paths = array_values((array) $this->argument('path'));

        foreach ($paths as $path) {
            if (! is_file($path)) {
                $this->error("File not found: {$path}");

                return self::FAILURE;
            }
        }

        [$lotARows, $lotCCabinets] = $this->parse($paths);

        $count = 0;

        // Lot A: one telemetry point per CSV row — copy its own coordinate
        // straight onto the device; cabinet_code is just a display label.
        foreach ($lotARows->chunk(self::CHUNK) as $chunk) {
            foreach ($chunk as $row) {
                Device::updateOrCreate(
                    ['vendor' => Vendor::LuminaP2P->value, 'external_id' => $row['device_id']],
                    [
                        'granularity' => Granularity::Point,
                        'label' => $row['point_code'],
                        'lat' => $row['lat'],
                        'lng' => $row['lng'],
                        'cabinet_code' => $row['cabinet_code'],
                        'cabinet_name' => $row['cabinet_name'],
                    ],
                );
                $count++;
            }

            $this->info('Lot A: '.min($count, count($lotARows)).' / '.count($lotARows).' points..');
        }

        // Lot C: CSV rows are per physical point with no device_id — the
        // reporting device is the cabinet itself. One representative
        // coordinate (average of the physical points) lands on the device.
        foreach ($lotCCabinets as $code => $cabinet) {
            Device::updateOrCreate(
                ['vendor' => Vendor::Cp3000->value, 'external_id' => $code],
                [
                    'granularity' => Granularity::Line,
                    'label' => $cabinet['name'],
                    'lat' => round($cabinet['lat_sum'] / $cabinet['points'], 7),
                    'lng' => round($cabinet['lng_sum'] / $cabinet['points'], 7),
                    'cabinet_code' => $code,
                    'cabinet_name' => $cabinet['name'],
                ],
            );
            $count++;
        }

        $this->info('Imported: '.count($lotARows).' Lot A points, '.count($lotCCabinets).' Lot C cabinets.');

        // Lot B (NemaCtrl) rows are skipped: the vendor is documented but
        // not live in ingestion (PRD §1/§3) — nothing to attach metadata to.

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $paths
     * @return array{Collection<int, array<string, mixed>>, array<string, array{ name: ?string, lat_sum: float, lng_sum: float, points: int }>}
     */
    private function parse(array $paths): array
    {
        $lotA = collect();
        $lotC = [];

        foreach ($paths as $path) {
            $handle = fopen($path, 'r');

            if ($handle === false) {
                continue;
            }

            try {
                $header = fgetcsv($handle);

                if ($header === false) {
                    continue;
                }

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 11) {
                        continue;
                    }

                    [
                        $lot, $cabinetCode, $cabinetName, , $pointCode,
                        $deviceId, , , $lat, $lng,
                    ] = $row;

                    $lat = (float) $lat;
                    $lng = (float) $lng;

                    if ($lot === 'A' && $deviceId !== '') {
                        $lotA->add([
                            'device_id' => $deviceId,
                            'point_code' => $pointCode,
                            'cabinet_code' => $cabinetCode,
                            'cabinet_name' => $cabinetName,
                            'lat' => $lat,
                            'lng' => $lng,
                        ]);
                    } elseif ($lot === 'C') {
                        $cabinet = $lotC[$cabinetCode] ?? ['name' => $cabinetName, 'lat_sum' => 0.0, 'lng_sum' => 0.0, 'points' => 0];
                        $cabinet['lat_sum'] += $lat;
                        $cabinet['lng_sum'] += $lng;
                        $cabinet['points']++;
                        $lotC[$cabinetCode] = $cabinet;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        return [$lotA, $lotC];
    }
}
