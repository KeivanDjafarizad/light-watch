<?php

namespace App\Models;

enum CommandType: string
{
    case On = 'on';
    case Off = 'off';
    case Dim = 'dim';
}
