<?php

namespace App\Models;

enum CommandStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acked = 'acked';
    case Failed = 'failed';
    case ConfirmedByTelemetry = 'confirmed_by_telemetry';
    case Unconfirmed = 'unconfirmed';

    /** @return list<self> statuses a command can still transition from */
    public static function active(): array
    {
        return [self::Pending, self::Sent];
    }

    public function isTerminal(): bool
    {
        return ! in_array($this, self::active(), true);
    }
}
