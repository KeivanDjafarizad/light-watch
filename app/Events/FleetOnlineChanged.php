<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sparse stream of online/offline flips only (never a per-tick heartbeat,
 * PRD §6): broadcast on the `fleet` channel whenever the online-detection
 * phase of the broadcast loop changes a device's `is_online`.
 *
 * @phpstan-type OnlineChange array{device_id: int, vendor: string, cabinet_code: ?string, is_online: bool}
 */
final class FleetOnlineChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** @param list<OnlineChange> $changes */
    public function __construct(
        public readonly array $changes,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('fleet')];
    }

    public function broadcastAs(): string
    {
        return 'online_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['changes' => $this->changes];
    }
}
