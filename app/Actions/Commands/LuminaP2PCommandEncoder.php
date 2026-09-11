<?php

namespace App\Actions\Commands;

use App\Models\Command;
use App\Models\CommandType;
use App\Models\Vendor;

/**
 * Lot A — Lumina P2P: point-to-point JSON commands on
 * `lumina/v2/sanverano/{node}/cmd`, explicitly acked by the vendor on
 * `lumina/v2/sanverano/{node}/ack` (PRD §10).
 */
final class LuminaP2PCommandEncoder implements VendorCommandEncoder
{
    public function supports(Vendor $vendor): bool
    {
        return $vendor === Vendor::LuminaP2P;
    }

    public function encode(Command $command): EncodedCommand
    {
        $op = match ($command->type) {
            CommandType::On => 'on',
            CommandType::Off => 'off',
            CommandType::Dim => 'dim',
        };

        $payload = [
            // The vendor echoes this id back in the ack: we use the command's
            // own id so ProcessLuminaAck can resolve it without extra state.
            'id' => (string) $command->id,
            'op' => $op,
            'value' => $command->type === CommandType::Dim
                ? (int) ($command->payload['level'] ?? 0)
                : 0,
            'ttl_s' => (int) config('dashboard.commands.lumina_ttl_s'),
        ];

        return new EncodedCommand(
            topic: sprintf('lumina/v2/sanverano/%s/cmd', $command->device->external_id),
            payload: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
