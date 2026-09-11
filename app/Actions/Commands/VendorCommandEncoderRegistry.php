<?php

namespace App\Actions\Commands;

use App\Models\Vendor;

final class VendorCommandEncoderRegistry
{
    /** @param list<VendorCommandEncoder> $encoders */
    public function __construct(
        private readonly array $encoders,
    ) {}

    public function for(Vendor $vendor): ?VendorCommandEncoder
    {
        foreach ($this->encoders as $encoder) {
            if ($encoder->supports($vendor)) {
                return $encoder;
            }
        }

        return null;
    }
}
