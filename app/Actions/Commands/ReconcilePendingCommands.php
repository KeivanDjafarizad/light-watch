<?php

namespace App\Actions\Commands;

use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\CommandType;
use App\Models\Device;
use App\Models\NormalizedEvent;
use App\Models\SwitchState;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lot C (CP-3000) reconciliation: after a fire-and-forget command is Sent,
 * incoming telemetry is checked against the intended state. First matching
 * reading -> ConfirmedByTelemetry. The window elapsing without a match is
 * handled by the expiry sweep (`ExpireStaleCommands`) -> Unconfirmed.
 *
 * Dim commands on CP-3000 can never match: the vendor reports no dim level,
 * so they honestly surface as Unconfirmed (PRD §10 — that is the system
 * working as intended, not a failure).
 */
final class ReconcilePendingCommands
{
    public function reconcile(NormalizedEvent $event): void
    {
        if ($event->vendor !== Vendor::Cp3000) {
            return; // Lot A is ack-based; telemetry reconciliation is a Lot C concern
        }

        DB::transaction(function () use ($event) {
            $device = Device::query()
                ->where('vendor', $event->vendor->value)
                ->where('external_id', $event->vendorDeviceId)
                ->first();

            if ($device === null) {
                return;
            }

            $commands = Command::query()
                ->where('device_id', $device->id)
                ->where('status', CommandStatus::Sent->value)
                ->whereNotNull('reconcile_by')
                ->where('reconcile_by', '>', now())
                ->lockForUpdate()
                ->get();

            foreach ($commands as $command) {
                if ($this->matches($command, $event)) {
                    $command->transitionTo(CommandStatus::ConfirmedByTelemetry, ['confirmed_at' => now()]);

                    Log::info("Command {$command->id} confirmed by telemetry for device {$device->id}.");
                }
            }
        });
    }

    private function matches(Command $command, NormalizedEvent $event): bool
    {
        return match ($command->type) {
            CommandType::On => $event->switchState === SwitchState::On,
            CommandType::Off => $event->switchState === SwitchState::Off,
            CommandType::Dim => $this->reportedDimLevel($event) === (int) ($command->payload['level'] ?? -1),
        };
    }

    /**
     * Dim level when the vendor reports one (Lumina reports `meas.dim`;
     * CP-3000 never does — hence null).
     */
    private function reportedDimLevel(NormalizedEvent $event): ?int
    {
        $dim = $event->rawPayload['meas']['dim'] ?? null;

        return is_numeric($dim) ? (int) $dim : null;
    }
}
