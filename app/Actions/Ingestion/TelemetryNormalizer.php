<?php

namespace App\Actions\Ingestion;

use App\Actions\Commands\ReconcilePendingCommands;
use App\Models\Alarm;
use App\Models\AlarmCode;
use App\Models\Device;
use App\Models\DeviceReading;
use App\Models\DeviceState;
use App\Models\NormalizedEvent;
use Couchbase\QueryException;
use Illuminate\Support\Facades\DB;

final class TelemetryNormalizer
{
    public function __construct(
        private readonly ReconcilePendingCommands $reconciler,
    ) {}

    public function apply(NormalizedEvent $event): void
    {
        $persisted = DB::transaction(function () use ($event) {
            $device = Device::firstOrCreate(
                ['vendor' => $event->vendor->value, 'external_id' => $event->vendorDeviceId],
                ['granularity' => $event->granularity->value],
            );

            // lockForUpdate: nessun altro worker può leggere/scrivere lo stato
            // di QUESTO device finché la transazione non chiude. Necessario perché
            // avremo più worker di coda in parallelo — senza il lock, due letture
            // quasi simultaneee dello stesso device potrebbero basarsi sullo stesso
            // "ultimo valore noto" e calcolare due delta entrambi sbagliati.
            $state = DeviceState::lockForUpdate()->find($device->id)
                ?? DeviceState::create(['device_id' => $device->id]);

            [$delta, $resetDetected] = $this->computeDelta(
                $state->last_energy_wh_cumulative,
                $event->energyWhCumulative,
            );

            try {
                DeviceReading::create([
                    'device_id' => $device->id,
                    'received_at' => $event->receivedAt,
                    'device_reported_at' => $event->deviceReportedAt,
                    'power_w' => $event->powerW,
                    'energy_wh_cumulative' => $event->energyWhCumulative,
                    'energy_wh_delta' => $delta,
                    'counter_reset_detected' => $resetDetected,
                    'switch_state' => $event->switchState->value,
                    'alarm_codes' => array_map(fn (AlarmCode $c) => $c->value, $event->alarmCodes),
                    'raw_payload' => $event->rawPayload,
                    'dedup_key' => $event->dedupKey,
                ]);
            } catch (QueryException $e) {
                if (in_array($e->getCode(), [23000, '23000'], true)) {
                    return false; // già processato in un retry precedente: idempotente, non è un errore
                }
                throw $e;
            }

            // Aggiorno lo stato SEMPRE, anche in caso di reset: il prossimo evento
            // deve confrontarsi col valore attuale (post-reset), non con quello vecchio.
            $state->update([
                'last_energy_wh_cumulative' => $event->energyWhCumulative,
                'last_received_at' => $event->receivedAt,
                'last_seen_at' => $event->receivedAt,
                'last_power_w' => $event->powerW,
                'last_switch_state' => $event->switchState->value,
            ]);

            // Ciclo di vita allarmi (PRD §5): dentro la stessa transazione
            // per-device locked, così "allarmi aperti" è sempre coerente
            // con l'ultima lettura persistita.
            $this->syncAlarms($device->id, $event->alarmCodes, $event->receivedAt);

            return true;
        });

        // Fuori dalla transazione: la riconciliazione dei comandi Lot C
        // (fire-and-forget) verifica la lettura appena persistita contro
        // lo stato voluto da eventuali comandi in finestra (PRD §10).
        if ($persisted) {
            $this->reconciler->reconcile($event);
        }
    }

    /**
     * @param  list<AlarmCode>  $alarmCodes
     */
    private function syncAlarms(int $deviceId, array $alarmCodes, mixed $receivedAt): void
    {
        $open = Alarm::query()
            ->where('device_id', $deviceId)
            ->where('status', 'open')
            ->get()
            ->keyBy(fn (Alarm $alarm) => $alarm->code->value);

        $present = collect($alarmCodes)->keyBy(fn (AlarmCode $code) => $code->value);

        foreach ($present as $code) {
            if (! $open->has($code->value)) {
                Alarm::open($deviceId, $code, $receivedAt);
            }
        }

        foreach ($open as $codeValue => $alarm) {
            if (! $present->has($codeValue)) {
                $alarm->close($receivedAt);
            }
        }
    }

    /**
     * @return array{0: float|null, 1: bool}
     */
    private function computeDelta(?float $previous, ?float $current): array
    {
        return match (true) {
            $current === null, $previous === null => [null, false],       // evento senza energia (raro, difensivo)
            // prima lettura in assoluto per questo device
            $current < $previous => [null, true],      // reset: non calcolo un delta negativo
            default => [$current - $previous, false],
        };
    }
}
