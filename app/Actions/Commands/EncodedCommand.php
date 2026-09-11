<?php

namespace App\Actions\Commands;

final readonly class EncodedCommand
{
    public function __construct(
        public string $topic,
        public string $payload,
    ) {}
}
