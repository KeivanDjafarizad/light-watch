<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceState extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceStateFactory> */
    use HasFactory;

    protected $primaryKey = 'device_id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_received_at' => 'immutable_datetime',
        ];
    }
}
