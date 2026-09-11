<?php

namespace App\Actions\Ingestion;

final class VendorAdapterRegistry
{
    /** @param VendorAdapter[] $adapters */
    public function __construct(
        private readonly array $adapters
    ) {}

    public function for(string $topic): ?VendorAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($topic)) {
                return $adapter;
            }
        }

        return null;
    }
}
