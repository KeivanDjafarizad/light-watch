<?php

namespace App\Actions\Commands;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

/**
 * Thin publish-only MQTT wrapper. One short-lived connection per publish:
 * commands are rare operator actions, not a stream, so a persistent
 * dedicated connection is not worth the supervision complexity.
 *
 * Bound as a singleton so tests can swap it out via the container
 * (`$this->instance(MqttPublisher::class, $fake)`).
 */
class MqttPublisher
{
    /** @throws MqttClientException */
    public function publish(string $topic, string $payload, int $qos = MqttClient::QOS_AT_LEAST_ONCE): void
    {
        $host = config('services.mqtt.host');
        $port = (int) config('services.mqtt.port');

        $mqtt = new MqttClient($host, $port, 'command-publisher-'.uniqid());
        $mqtt->connect((new ConnectionSettings)->setKeepAliveInterval(60), true);

        try {
            $mqtt->publish($topic, $payload, $qos);
        } finally {
            $mqtt->disconnect();
        }
    }
}
