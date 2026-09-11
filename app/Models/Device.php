<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property Vendor $vendor
 * @property Granularity $granularity
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vendor' => Vendor::class,
            'granularity' => Granularity::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /** @return HasOne<DeviceState, $this> */
    public function state(): HasOne
    {
        return $this->hasOne(DeviceState::class);
    }

    /** @return HasMany<DeviceReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(DeviceReading::class);
    }

    /** @return HasMany<Alarm, $this> */
    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }

    /** @return HasMany<Command, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(Command::class);
    }

    /**
     * The cabinet this device reports for, as used by the dashboard for
     * grouping and the `cabinet.{code}` broadcast channel.
     *
     * Lot C devices are the cabinets themselves (`external_id` is the
     * cabinet code), so they always group even before `plant:import`
     * populates `cabinet_code`. Lot A points only group after the import.
     */
    public function cabinetCode(): ?string
    {
        return $this->cabinet_code
            ?? ($this->vendor === Vendor::Cp3000 ? $this->external_id : null);
    }
}
