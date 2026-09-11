<?php

namespace App\Jobs;

use App\Actions\Ingestion\TelemetryNormalizer;
use App\Actions\Ingestion\VendorAdapterRegistry;
use App\Models\RawMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NormalizeRawMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $rawMessageId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(VendorAdapterRegistry $registry, TelemetryNormalizer $normalizer): void
    {
        $raw = RawMessage::find($this->rawMessageId);
        if (! $raw || $raw->processed_at !== null) {
            return;
        }

        $adapter = $registry->for($raw->topic);
        if ($adapter === null) {
            Log::warning("No adapter found for topic: {$raw->topic}");
        }

        foreach ($adapter->normalize($raw->topic, $raw->payload, $raw->received_at) as $event) {
            $normalizer->apply($event);
        }

        $raw->update(['processed_at' => now()]);
    }
}
