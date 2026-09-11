<?php

namespace App\Events;

use App\Models\Command;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Status transition for an issued command, on the public `commands`
 * channel (PRD §8). Clients subscribe only while a command is outstanding.
 */
final class CommandStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly Command $command,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('commands')];
    }

    public function broadcastAs(): string
    {
        return 'status_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'command_id' => $this->command->id,
            'device_id' => $this->command->device_id,
            'type' => $this->command->type->value,
            'status' => $this->command->status->value,
            'updated_at' => $this->command->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
