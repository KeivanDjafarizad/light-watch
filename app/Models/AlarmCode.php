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

    public function defaultSeverity(): AlarmSeverity
    {
        return match ($this) {
            self::PhaseLoss, self::BreakerTripped => AlarmSeverity::Critical,
            self::OverCurrent, self::OverVoltage, self::UnderVoltage, self::OverTemperature, self::LampFault => AlarmSeverity::Warning,
            self::DoorOpen, self::ManualOverride, self::PowerFactorLow => AlarmSeverity::Info,
            self::Unknown => AlarmSeverity::Warning,
        };
    }
}
