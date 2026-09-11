<?php

namespace App\Models;

enum SwitchState: string
{
    case On = 'on';
    case Off = 'off';
    case Unknown = 'unknown';
}
