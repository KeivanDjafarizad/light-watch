<?php

namespace App\Models;

enum AlarmCode: string
{
    case LampFault = 'lamp_fault';
    case OverCurrent = 'over_current';
    case OverVoltage = 'over_voltage';
    case UnderVoltage = 'under_voltage';
    case OverTemperature = 'over_temperature';
    case PowerFactorLow = 'power_factor_low';
    case PhaseLoss = 'phase_loss';
    case DoorOpen = 'door_open';
    case BreakerTripped = 'breaker_tripped';
    case ManualOverride = 'manual_override';
    case Unknown = 'unknown';
}
