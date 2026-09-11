<?php

namespace App\Jobs;

use App\Models\Command;
use App\Models\CommandStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles a vendor ack received on `lumina/v2/sanverano/{node}/ack`
 * (Lot A only): `res: "ok"` -> Acked, `res: "err"` -> Failed.
 *
 * Acks can be lost (~6%) or arrive late; transitionTo guards against
 * double transitions and the timeout sweep in `dashboard:broadcast`
 * closes anything that never acks in time.
 */
class ProcessLuminaAck implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $topic,
        public readonly string $payload,
    ) {}

    public function handle(): void
    {
        $data = json_decode($this->payload, true);

        if (! is_array($data) || ! isset($data['id'], $data['res'])) {
            Log::warning("Malformed Lumina ack on {$this->topic}: {$this->payload}");

            return;
        }

        $command = Command::query()->find((int) $data['id']);

        if ($command === null || $command->status->isTerminal()) {
            return; // unknown or already settled (duplicate ack, lost timeout race): ignore
        }

        $command->transitionTo(
            $data['res'] === 'ok' ? CommandStatus::Acked : CommandStatus::Failed,
            [
                'acked_at' => now(),
                // vendor diagnostic (0 ok, 1 busy, 2 bad param, 3 hw fault,
                // 4 unsupported) — kept for the record, not for the state machine
                'ack_code' => isset($data['code']) && is_numeric($data['code']) ? (int) $data['code'] : null,
            ],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::warning("Failed to process Lumina ack on {$this->topic}: {$exception->getMessage()}");
    }
}
