<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DeviceStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $is_online
 * @property SwitchState|null $last_switch_state
 * @property CarbonImmutable|null $last_received_at
 * @property CarbonImmutable|null $last_seen_at
 */
class DeviceState extends Model
{
    /** @use HasFactory<DeviceStateFactory> */
    use HasFactory;

    protected $primaryKey = 'device_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_received_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'last_power_w' => 'float',
            'is_online' => 'boolean',
            'last_switch_state' => SwitchState::class,
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
