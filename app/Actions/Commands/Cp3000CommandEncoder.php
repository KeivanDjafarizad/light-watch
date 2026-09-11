<?php

namespace App\Actions\Commands;

use App\Models\Command;
use App\Models\CommandType;
use App\Models\Vendor;

/**
 * Lot C — CP-3000: cabinet-level fire-and-forget on `cp3000/{cabinet}/cmd`.
 * The vendor never acks; confirmation (when possible) comes from telemetry
 * reconciliation (PRD §10).
 *
 * Encoding assumption: the starter kit only documents `SET;DIM;0;70`, where
 * field 3 appears to be a group/line selector (0 = all lines). We mirror it
 * for on/off: `SET;ON;0;0` / `SET;OFF;0;0`. Noted in NOTES.md.
 */
final class Cp3000CommandEncoder implements VendorCommandEncoder
{
    public function supports(Vendor $vendor): bool
    {
        return $vendor === Vendor::Cp3000;
    }

    public function encode(Command $command): EncodedCommand
    {
        $op = match ($command->type) {
            CommandType::On => 'ON',
            CommandType::Off => 'OFF',
            CommandType::Dim => 'DIM',
        };

        $value = $command->type === CommandType::Dim
            ? (int) ($command->payload['level'] ?? 0)
            : 0;

        return new EncodedCommand(
            topic: sprintf('cp3000/%s/cmd', $command->device->external_id),
            payload: sprintf('SET;%s;0;%d', $op, $value),
        );
    }
}
