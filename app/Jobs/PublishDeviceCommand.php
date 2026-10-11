<?php

namespace App\Jobs;

use App\Mqtt\DeviceCommand;
use App\Mqtt\StaleDeviceCommandException;
use App\Services\MqttPublisherService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one manual approve/reject decision to its device.
 *
 * Runs on its own queue with its own worker, separate from the broadcast
 * events on the default queue: a slow broker can't hold up broadcasts, and
 * a broadcast backlog can't delay a gate command.
 *
 * Retries are deliberately tight, because a late "open" is more dangerous
 * than a failed one. The job retries 1 second apart only until the
 * command's TTL (retryUntil), the publisher refuses anything stale, and an
 * attempt that hangs — e.g. a broker that accepts the socket but never
 * answers CONNACK, which php-mqtt waits on forever — is killed by $timeout
 * and fails the job outright instead of being retried.
 */
class PublishDeviceCommand implements ShouldQueue
{
    use Queueable;

    public const QUEUE = 'device-commands';

    /**
     * Seconds before the worker kills a hung attempt. Enforced with the
     * pcntl extension's SIGALRM, so it only works where pcntl is loaded.
     */
    public int $timeout;

    public bool $failOnTimeout = true;

    /** Seconds between attempts. */
    public int $backoff = 1;

    public function __construct(public DeviceCommand $command)
    {
        $this->onQueue(self::QUEUE);

        // A normal attempt needs at most connect_timeout + puback_timeout;
        // the margin covers the rest of the attempt (disconnect, logging).
        $this->timeout = (int) config('mqtt.connect_timeout') + (int) config('mqtt.puback_timeout') + 3;
    }

    /**
     * Read by the worker before every attempt: once this passes, the job is
     * failed without running, so a worker that was down never sends a
     * command late when it comes back.
     */
    public function retryUntil(): DateTimeInterface
    {
        return Date::createFromTimestamp($this->command->staleAt());
    }

    public function handle(MqttPublisherService $publisher): void
    {
        try {
            $publisher->publish($this->command);
        } catch (StaleDeviceCommandException $e) {
            // Retrying can only make it staler.
            $this->fail($e);
        }
    }

    /**
     * Called once, when the job gives up for good: TTL passed, a stale
     * command was refused, or an attempt hung past $timeout.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('PublishDeviceCommand: device command was never delivered.', [
            'access_log_id' => $this->command->accessLogId,
            'device_id' => $this->command->deviceId,
            'action' => $this->command->action->value,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);
    }
}
