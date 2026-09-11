<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Aggregated fleet KPIs, broadcast on the public `fleet` channel by the
 * `dashboard:broadcast` loop (every few seconds, never per-raw-event).
 */
final class FleetSnapshot implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array{points_online: int, points_offline: int, points_total: int, power_w_total: float, active_alarms: int, generated_at: string}  $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('fleet')];
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
