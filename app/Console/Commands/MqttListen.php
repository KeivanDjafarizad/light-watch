<?php

namespace App\Console\Commands;

use App\Jobs\NormalizeRawMessage;
use App\Models\RawMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

#[Signature('mqtt:listen')]
#[Description('Ascolta i topic del campo e logga i messaggi')]
class MqttListen extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = config('services.mqtt.host');
        $port = config('services.mqtt.port');

        $mqtt = new MqttClient($host, $port, 'ingestion-worker-'.uniqid());
        $settings = (new ConnectionSettings())->setKeepAliveInterval(60);

        $this->info("Connessione a mqtt://{$host}:{$port}..");
        $mqtt->connect($settings, true);
        $this->info('Connesso!');

        $mqtt->subscribe('lumina/v2/sanverano/+/telemetry', function (string $topic, string $message) {
            $this->persistRaw('A', $topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $mqtt->subscribe('cp3000/+/data', function (string $topic, string $message) {
            $this->persistRaw('C', $topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $mqtt->loop(true);

        return self::SUCCESS;
    }

    private function logRaw(string $lot, string $topic, string $payload): void
    {
        $receivedAt = now()->toIso8601String();

        $this->line("[{$receivedAt}] {$lot} - {$topic} - {$payload}]");
        $this->line(' ' . mb_strimwidth($payload, 0, 160, '...'));
    }

    private function persistRaw(string $lot, string $topic, string $payload): void
    {
        $receivedAt = now();
        $dedupKey = hash('xxh128', $topic . $payload);

        try {
            $rawMessage = RawMessage::create([
               'lot' => $lot,
               'topic' => $topic,
               'payload' => $payload,
               'received_at' => $receivedAt,
               'dedup_key' => $dedupKey,
            ]);
            NormalizeRawMessage::dispatch($rawMessage->id);
            $this->line("[{$receivedAt->toIso8601String()}] [{$lot}] saved: {$topic} }]");
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $this->line("[{$receivedAt->toIso8601String()}] [{$lot}] duplicato scartato: {$topic}");
                return;
            }
            throw $e;
        }
    }
}
