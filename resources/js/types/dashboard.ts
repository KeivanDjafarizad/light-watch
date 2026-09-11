/**
 * Dashboard data contracts (PRD §7/§8) — mirrored from the backend.
 */

export type FleetSnapshot = {
    points_online: number;
    points_offline: number;
    points_total: number;
    power_w_total: number;
    active_alarms: number;
    generated_at: string;
};

export type CabinetRow = {
    cabinet_code: string;
    cabinet_name: string | null;
    vendor: string;
    points_online: number;
    points_offline: number;
    power_w: number;
    is_online: boolean;
    active_alarms: number;
    worst_alarm_severity: 'critical' | 'warning' | 'info' | null;
    lat: number | null;
    lng: number | null;
    devices: number[];
};

export type CabinetStatus = 'critical' | 'warning' | 'ok' | 'offline';

/** Worst-case status, not an average (PRD §9.2): any open critical alarm → red,
 * else any offline points → amber, else green. */
export function cabinetStatus(
    row: Pick<
        CabinetRow,
        'worst_alarm_severity' | 'points_offline' | 'is_online'
    >,
): CabinetStatus {
    if (row.worst_alarm_severity === 'critical') {
        return 'critical';
    }

    if (row.points_offline > 0 || !row.is_online) {
        return 'warning';
    }

    return 'ok';
}

export type SeriesPoint = {
    t: string;
    power_w: number;
};

export type CabinetDevice = {
    id: number;
    external_id: string;
    label: string | null;
    vendor: string;
    is_online: boolean;
    switch_state: 'on' | 'off' | 'unknown' | null;
    power_w: number | null;
};

export type CommandDto = {
    id: number;
    device_id: number;
    type: 'on' | 'off' | 'dim';
    payload: { level?: number } | null;
    status: CommandStatus;
    issued_at: string;
    sent_at: string | null;
    acked_at: string | null;
    /** Lot A vendor diagnostic: 0 ok, 1 busy, 2 bad param, 3 hw fault, 4 unsupported */
    ack_code: number | null;
    confirmed_at: string | null;
    reconcile_by: string | null;
};

export type CommandStatus =
    | 'pending'
    | 'sent'
    | 'acked'
    | 'failed'
    | 'confirmed_by_telemetry'
    | 'unconfirmed';

export type CabinetDetail = {
    cabinet: CabinetRow;
    devices: CabinetDevice[];
    series: SeriesPoint[];
    outstanding_commands: CommandDto[];
};

export type AlarmDto = {
    id: number;
    device_id: number;
    device: {
        id: number;
        external_id: string;
        vendor: string;
        label: string | null;
        cabinet_code: string | null;
    } | null;
    code: string;
    severity: 'critical' | 'warning' | 'info';
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
};

/** Visual grouping of command terminal states (PRD §9.4). */
export function commandOutcome(
    status: CommandStatus,
): 'pending' | 'confirmed' | 'failed' | 'unconfirmed' {
    switch (status) {
        case 'acked':
        case 'confirmed_by_telemetry':
            return 'confirmed';
        case 'failed':
            return 'failed';
        case 'unconfirmed':
            return 'unconfirmed';
        default:
            return 'pending';
    }
}

export function formatWatts(watts: number): string {
    if (watts >= 1_000_000) {
        return `${(watts / 1_000_000).toFixed(2)} MW`;
    }

    if (watts >= 1_000) {
        return `${(watts / 1_000).toFixed(1)} kW`;
    }

    return `${Math.round(watts)} W`;
}
