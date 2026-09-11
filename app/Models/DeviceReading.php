<?php

namespace App\Models;

use Database\Factories\DeviceReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'alarm_codes' => 'array',
            'raw_payload' => 'array',
        ];
    }
}
