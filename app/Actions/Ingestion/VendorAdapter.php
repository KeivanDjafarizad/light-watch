<?php

namespace App\Actions\Ingestion;

use App\Models\NormalizedEvent;
use DateTimeImmutable;

interface VendorAdapter
{
    public function supports(string $topic): bool;

    /** @return NormalizedEvent[] */
    public function normalize(string $topic, string $rawPayload, DateTimeImmutable $receivedAt): array;
}
