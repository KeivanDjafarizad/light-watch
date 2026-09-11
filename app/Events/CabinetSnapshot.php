<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Per-cabinet snapshot, broadcast on the public `cabinet.{code}` channel.
 * Clients subscribe only while viewing that cabinet's drill-down (PRD §6/§9.3).
 */
final class CabinetSnapshot implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array{cabinet_code: string, power_w: float, is_online: bool, points_online: int, points_offline: int, active_alarms: int, generated_at: string}  $payload
     */
    public function __construct(
        public readonly string $cabinetCode,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('cabinet.'.$this->cabinetCode)];
    }

    public function broadcastAs(): string
    {
        return 'snapshot';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
