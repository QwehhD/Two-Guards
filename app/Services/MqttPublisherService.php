<?php

namespace App\Services;

use App\Mqtt\DeviceCommand;
use App\Mqtt\DeviceCommandNotAcknowledgedException;
use App\Mqtt\StaleDeviceCommandException;
use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\MqttClient as PhpMqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

/**
 * Publishes one DeviceCommand over its own short-lived connection, and only
 * reports success once the broker has acknowledged it (QoS 1 PUBACK).
 *
 * Used by the queued PublishDeviceCommand job (manual approve/reject). It
 * deliberately connects, publishes, and disconnects on every call: it runs
 * in a queue worker process, which can never share a connection with the
 * long-running mqtt:listen daemon. Each call also gets its own random
 * client ID suffix, because two clients connecting with the same ID would
 * make the broker disconnect whichever one held it first — the daemon's
 * "-listener" ID must stay untouched by this service.
 */
class MqttPublisherService
{
    /**
     * @param  (callable(string $clientId, Repository $repository): MqttClient)|null  $clientFactory  Overridable so tests can inject a mock instead of opening a real socket. The client must track its unacknowledged messages in the given repository.
     */
    public function __construct(private readonly mixed $clientFactory = null) {}

    /**
     * @throws StaleDeviceCommandException when the command's TTL ran out before it could be sent
     * @throws DeviceCommandNotAcknowledgedException when the broker sent no PUBACK in time
     */
    public function publish(DeviceCommand $command): void
    {
        $this->ensureNotStale($command);

        // php-mqtt keeps every QoS 1 message it sent in this repository until
        // the matching PUBACK arrives, so whatever is still in it after the
        // wait below was never acknowledged by the broker.
        $repository = new MemoryRepository;
        $clientId = config('mqtt.client_id_prefix').'-publisher-'.Str::random(8);
        $client = $this->makeClient($clientId, $repository);

        // The connect timeout replaces php-mqtt's 60-second default so an
        // unreachable broker fails fast instead of eating the command's
        // TTL. It covers the TCP connect, but not a broker that accepts
        // the socket and then never answers the MQTT handshake: php-mqtt
        // waits for the first CONNACK byte without any deadline, which is
        // what PublishDeviceCommand's $timeout is there to cut off.
        $settings = (new ConnectionSettings)
            ->setUsername(config('mqtt.username') ?: null)
            ->setPassword(config('mqtt.password') ?: null)
            ->setConnectTimeout((int) config('mqtt.connect_timeout'));

        // A clean session (2nd arg) is appropriate here: this client never
        // subscribes to anything and disconnects right after, so there is
        // no session state worth the broker retaining.
        $client->connect($settings, true);

        try {
            // Connecting may itself have taken up to connect_timeout, so
            // check again right before the PUBLISH leaves: a command must
            // never reach the broker after its TTL.
            $this->ensureNotStale($command);

            $client->publish($command->topic(), $command->payload(), qualityOfService: 1);

            // publish() only writes the packet; the PUBACK is read by loop().
            // With exitWhenQueuesEmpty it returns as soon as nothing is left
            // unacknowledged, or silently once puback_timeout has passed.
            $pubackTimeout = (int) config('mqtt.puback_timeout');
            $client->loop(true, true, $pubackTimeout);

            if ($repository->countPendingOutgoingMessages() > 0) {
                throw DeviceCommandNotAcknowledgedException::for($command, $pubackTimeout);
            }
        } finally {
            $client->disconnect();
        }
    }

    private function ensureNotStale(DeviceCommand $command): void
    {
        if ($command->isStale()) {
            throw StaleDeviceCommandException::for($command);
        }
    }

    private function makeClient(string $clientId, Repository $repository): MqttClient
    {
        if ($this->clientFactory !== null) {
            return ($this->clientFactory)($clientId, $repository);
        }

        return new PhpMqttClient(config('mqtt.host'), (int) config('mqtt.port'), $clientId, repository: $repository);
    }
}
