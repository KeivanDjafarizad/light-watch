<?php

namespace App\Models;

use DateTimeImmutable;

final class NormalizedEvent
{
    public function __construct(
        public readonly Vendor $vendor,
        public readonly string $vendorDeviceId,
        public readonly Granularity $granularity,
        public readonly DateTimeImmutable $receivedAt,
        public readonly ?DateTimeImmutable $deviceReportedAt,
        public readonly ?float $powerW,
        public readonly ?float $energyWhCumulative,
        public readonly SwitchState $switchState,
        public readonly array $alarmCodes,
        public readonly string $dedupKey,
        public readonly array $rawPayload,
    ) {}
}
