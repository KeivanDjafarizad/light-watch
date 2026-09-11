<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RawMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 */
class RawMessage extends Model
{
    /** @use HasFactory<RawMessageFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
