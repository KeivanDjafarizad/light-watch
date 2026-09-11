<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeviceReadingFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property SwitchState $switch_state
 * @property Collection<int, AlarmCode>|null $alarm_codes
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $device_reported_at
 */
class DeviceReading extends Model
{
    /** @use HasFactory<DeviceReadingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'device_reported_at' => 'immutable_datetime',
            'counter_reset_detected' => 'boolean',
            'power_w' => 'float',
            'switch_state' => SwitchState::class,
            'alarm_codes' => AsEnumCollection::class.':'.AlarmCode::class,
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
