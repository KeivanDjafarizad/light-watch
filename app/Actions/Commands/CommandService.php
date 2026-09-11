<?php

namespace App\Actions\Commands;

use App\Jobs\PublishCommand;
use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\CommandType;
use App\Models\Device;

/**
 * Issues a control command: persists it as Pending and dispatches the
 * queued publish job. The caller gets the command back immediately —
 * the MQTT publish and any subsequent ack/reconciliation happen out of
 * band (PRD §7: never block the HTTP response on the publish).
 */
final class CommandService
{
    public function __construct(
        private readonly VendorCommandEncoderRegistry $encoders,
    ) {}

    /**
     * @param  array{level?: int}|null  $payload  required for dim commands
     */
    public function issue(Device $device, CommandType $type, ?array $payload = null): Command
    {
        if ($this->encoders->for($device->vendor) === null) {
            throw new \InvalidArgumentException("No command encoder for vendor [{$device->vendor->value}].");
        }

        $command = Command::create([
            'device_id' => $device->id,
            'type' => $type,
            'payload' => $type === CommandType::Dim ? ['level' => (int) ($payload['level'] ?? 0)] : null,
            'status' => CommandStatus::Pending,
            'issued_at' => now(),
        ]);

        PublishCommand::dispatch($command->id);

        return $command;
    }
}
