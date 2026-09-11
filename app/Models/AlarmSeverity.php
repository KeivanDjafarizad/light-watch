<?php

namespace App\Models;

enum AlarmSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }

    /** @param list<self> $severities */
    public static function worstOf(array $severities): ?self
    {
        return array_reduce(
            $severities,
            fn (?self $worst, self $current) => $current->rank() > ($worst?->rank() ?? 0) ? $current : $worst,
            null,
        );
    }
}
