<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\MqttClient as PhpMqttClient;

/**
 * Publishes a device command ("open" or "deny") to parkir/{device_id}/command.
 *
 * This deliberately connects, publishes, and disconnects on every call —
 * it does NOT keep a persistent connection open. It runs inside the HTTP
 * process handling approve/reject/simulate-scan requests (Tahap 6/9),
 * which is a different OS process per request and can never share a
 * connection with the long-running mqtt:listen daemon (Tahap 9 point 4).
 * Each call also gets its own random client ID suffix, because two
 * clients connecting with the same ID would make the broker disconnect
 * whichever one held it first — the daemon's "-listener" ID must stay
 * untouched by this service.
 */
class MqttPublisherService
{
    private const ALLOWED_ACTIONS = ['open', 'deny'];

    /**
     * @param  (callable(string $clientId): MqttClient)|null  $clientFactory  Overridable so tests can inject a mock instead of opening a real socket.
     */
    public function __construct(private readonly mixed $clientFactory = null) {}

    public function publishCommand(int $deviceId, string $action): void
    {
        if (! in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new InvalidArgumentException(
                "Unknown MQTT command action [{$action}]. Expected one of: ".implode(', ', self::ALLOWED_ACTIONS)
            );
        }

        $clientId = config('mqtt.client_id_prefix').'-publisher-'.Str::random(8);
        $client = $this->makeClient($clientId);

        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username') ?: null)
            ->setPassword(config('mqtt.password') ?: null);

        // A clean session (2nd arg) is appropriate here: this client never
        // subscribes to anything and disconnects immediately after, so
        // there is no session state worth the broker retaining.
        $client->connect($settings, true);

        try {
            $client->publish(
                "parkir/{$deviceId}/command",
                json_encode(['action' => $action], JSON_THROW_ON_ERROR),
                qualityOfService: 1,
            );
        } finally {
            $client->disconnect();
        }
    }

    private function makeClient(string $clientId): MqttClient
    {
        if ($this->clientFactory !== null) {
            return ($this->clientFactory)($clientId);
        }

        return new PhpMqttClient(config('mqtt.host'), (int) config('mqtt.port'), $clientId);
    }
}
