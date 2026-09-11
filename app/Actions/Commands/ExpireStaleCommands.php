<?php

namespace App\Actions\Commands;

use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\Vendor;

/**
 * Command expiry sweep, run on every tick of the `dashboard:broadcast`
 * loop (the 1-minute scheduler floor is too coarse for the Lot A ack
 * timeout, PRD §6):
 *
 * - Lot A: Sent for longer than the ack timeout without an ack -> Failed.
 * - Lot C: Sent with a reconciliation window that has elapsed without a
 *   matching reading -> Unconfirmed (NOT Failed: fire-and-forget means
 *   we genuinely do not know what happened).
 */
final class ExpireStaleCommands
{
    /**
     * @return list<Command> the commands that were transitioned
     */
    public function expire(): array
    {
        $expired = [];

        $stale = Command::query()
            ->with('device')
            ->where('status', CommandStatus::Sent->value)
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query->whereHas('device', fn ($device) => $device->where('vendor', Vendor::LuminaP2P->value))
                            ->where('sent_at', '<', now()->subSeconds((int) config('vendors.lumina.ack_timeout', 30)));
                    })
                    ->orWhere(function ($query) {
                        $query->whereHas('device', fn ($device) => $device->where('vendor', Vendor::Cp3000->value))
                            ->whereNotNull('reconcile_by')
                            ->where('reconcile_by', '<=', now());
                    });
            })
            ->get();

        foreach ($stale as $command) {
            $status = $command->device->vendor === Vendor::Cp3000
                ? CommandStatus::Unconfirmed
                : CommandStatus::Failed;

            if ($command->transitionTo($status)) {
                $expired[] = $command;
            }
        }

        return $expired;
    }
}
