<?php

namespace App\Jobs;

use App\Actions\Commands\MqttPublisher;
use App\Actions\Commands\VendorCommandEncoderRegistry;
use App\Models\Command;
use App\Models\CommandStatus;
use App\Models\Vendor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes a Pending command to the vendor MQTT broker and moves it to
 * Sent. Lot C (CP-3000) commands additionally open the reconciliation
 * window (PRD §10): fire-and-forget, to be confirmed by telemetry or
 * honestly left Unconfirmed.
 */
class PublishCommand implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $commandId,
    ) {}

    public function handle(VendorCommandEncoderRegistry $encoders, MqttPublisher $publisher): void
    {
        $command = Command::query()->with('device')->find($this->commandId);

        if ($command === null || $command->status !== CommandStatus::Pending) {
            return; // already sent or terminal (retry after a late success): idempotent no-op
        }

        $encoder = $encoders->for($command->device->vendor);

        if ($encoder === null) {
            $command->transitionTo(CommandStatus::Failed);

            return;
        }

        $encoded = $encoder->encode($command);

        $publisher->publish($encoded->topic, $encoded->payload);

        $attributes = ['sent_at' => now()];

        if ($command->device->vendor === Vendor::Cp3000) {
            $attributes['reconcile_by'] = now()->addSeconds((int) config('dashboard.commands.reconcile_window_seconds'));
        }

        $command->transitionTo(CommandStatus::Sent, $attributes);
    }

    /**
     * The queue exhausted its retries (broker unreachable, etc.): the
     * command never left the building — that is a failure, not an
     * "unconfirmed" (we know it was not sent).
     */
    public function failed(Throwable $exception): void
    {
        Log::warning("PublishCommand {$this->commandId} failed permanently: {$exception->getMessage()}");

        $command = Command::query()->find($this->commandId);

        if ($command !== null && ! $command->status->isTerminal()) {
            $command->transitionTo(CommandStatus::Failed);
        }
    }
}
