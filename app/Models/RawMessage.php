<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMessage extends Model
{
    /** @use HasFactory<\Database\Factories\RawMessageFactory> */
    use HasFactory;

    protected $guarded = [];
}
