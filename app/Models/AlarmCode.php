<?php

namespace App\Models;

enum AlarmCode: string
{
    case LampFault = 'lamp_fault';
    case PhaseLoss = 'phase_loss';
    case DoorOpen = 'door_open';
    case Unknown = 'unknown';
}
