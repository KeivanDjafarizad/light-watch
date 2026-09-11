<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AlarmFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property AlarmCode $code
 * @property AlarmSeverity $severity
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $closed_at
 */
class Alarm extends Model
{
    /** @use HasFactory<AlarmFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'code' => AlarmCode::class,
            'severity' => AlarmSeverity::class,
            'status' => 'string',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public static function open(int $deviceId, AlarmCode $code, mixed $openedAt): self
    {
        return static::query()->create([
            'device_id' => $deviceId,
            'code' => $code,
            'severity' => $code->defaultSeverity(),
            'status' => 'open',
            'opened_at' => $openedAt,
        ]);
    }

    public function close(mixed $closedAt): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => $closedAt,
        ]);
    }
}
