<?php

namespace App\Actions\Commands;

use App\Models\Command;
use App\Models\Vendor;

/**
 * Vendor-specific encoding of an outgoing command, mirroring the ingestion
 * VendorAdapter pattern: adding a third vendor's command support later is
 * "add one class" plus one registry entry (PRD §10).
 */
interface VendorCommandEncoder
{
    public function supports(Vendor $vendor): bool;

    /** Encoded MQTT topic + payload ready to be published for this command. */
    public function encode(Command $command): EncodedCommand;
}
