<?php

namespace App\Models;

enum Granularity: string
{
    case Point = 'point';
    case Line = 'line';
}
