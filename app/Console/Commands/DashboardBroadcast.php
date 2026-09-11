<?php

namespace App\Console\Commands;

use App\Actions\Commands\ExpireStaleCommands;
use App\Actions\Dashboard\BuildCabinetSummaries;
use App\Actions\Dashboard\BuildFleetSnapshot;
use App\Actions\Dashboard\DetectOnlineFlips;
use App\Events\CabinetSnapshot;
use App\Events\FleetSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('dashboard:broadcast {--interval= : Seconds between ticks (defaults to dashboard.broadcast_interval_seconds)}')]
#[Description('Long-running loop: broadcasts fleet/cabinet snapshots, flips online state and expires stale commands')]
class DashboardBroadcast extends Command
{
    /**
     * Execute the console command.
     *
     * Supervised like mqtt:listen (restart-on-crash). Laravel's scheduler
     * has a 1-minute floor, so the 5-10s cadence required by PRD §6 needs
     * an internal sleep loop instead.
     */
    public function handle(
        BuildFleetSnapshot $fleetSnapshot,
        BuildCabinetSummaries $cabinetSummaries,
        DetectOnlineFlips $detectOnlineFlips,
        ExpireStaleCommands $expireStaleCommands,
    ): int {
        $interval = (float) ($this->option('interval') ?: config('dashboard.broadcast_interval_seconds'));

        $this->info("Broadcasting dashboard snapshots every {$interval}s (Ctrl+C to stop)...");

        for (; ;) {
            $started = microtime(true);

            try {
                $this->tick($fleetSnapshot, $cabinetSummaries, $detectOnlineFlips, $expireStaleCommands);
            } catch (Throwable $e) {
                // A transient DB/broker blip must not kill the loop:
                // report and carry on with the next tick.
                report($e);
                $this->error('tick failed: '.$e->getMessage());
            }

            $sleep = max(0, $interval - (microtime(true) - $started));
            usleep((int) ($sleep * 1_000_000));
        }
    }

    private function tick(
        BuildFleetSnapshot $fleetSnapshot,
        BuildCabinetSummaries $cabinetSummaries,
        DetectOnlineFlips $detectOnlineFlips,
        ExpireStaleCommands $expireStaleCommands,
    ): void {
        $generatedAt = now()->toIso8601String();

        // 1. Fleet KPI snapshot on `fleet`.
        FleetSnapshot::dispatch($fleetSnapshot->build());

        // 2. One snapshot per cabinet on `cabinet.{code}`. Clients only
        //    receive what they subscribed to, so ~15 channels is cheap.
        foreach ($cabinetSummaries->build() as $cabinet) {
            CabinetSnapshot::dispatch($cabinet->code, $cabinet->toSnapshotPayload($generatedAt));
        }

        // 3. Online/offline flips: sparse broadcast of changes only (PRD §6).
        $detectOnlineFlips->detectAndBroadcast();

        // 4. Command expiry: Lot A ack timeouts, Lot C reconciliation
        //    windows past their deadline.
        $expired = $expireStaleCommands->expire();

        if ($expired !== []) {
            $this->line(sprintf('[%s] expired %d stale command(s)', now()->toIso8601String(), count($expired)));
        }
    }
}
