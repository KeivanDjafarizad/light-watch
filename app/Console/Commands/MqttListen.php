<?php

namespace App\Console\Commands;

use App\Jobs\NormalizeRawMessage;
use App\Jobs\ProcessLuminaAck;
use App\Models\RawMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

#[Signature('mqtt:listen')]
#[Description('Listen to the plant MQTT topics and log incoming messages')]
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
        $settings = (new ConnectionSettings)->setKeepAliveInterval(60);

        $this->info("Connecting to mqtt://{$host}:{$port}..");
        $mqtt->connect($settings, true);
        $this->info('Connected!');

        $mqtt->subscribe('lumina/v2/sanverano/+/telemetry', function (string $topic, string $message) {
            $this->persistRaw('A', $topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $mqtt->subscribe('cp3000/+/data', function (string $topic, string $message) {
            $this->persistRaw('C', $topic, $message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        // Lot A command acks (PRD §12): explicit vendor confirmation for
        // commands issued from the dashboard. Not telemetry — dispatched
        // straight to the ack processor, no raw_messages row.
        $mqtt->subscribe('lumina/v2/sanverano/+/ack', function (string $topic, string $message) {
            ProcessLuminaAck::dispatch($topic, $message);
            $this->line('['.now()->toIso8601String().'] [ack] '.$topic.' '.$message);
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $mqtt->loop(true);

        return self::SUCCESS;
    }

    private function persistRaw(string $lot, string $topic, string $payload): void
    {
        $receivedAt = now();
        $dedupKey = hash('xxh128', $topic.$payload);

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
                $this->line("[{$receivedAt->toIso8601String()}] [{$lot}] duplicate discarded: {$topic}");

                return;
            }
            throw $e;
        }
    }
}
