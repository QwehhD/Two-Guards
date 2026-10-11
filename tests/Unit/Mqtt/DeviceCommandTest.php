<?php

namespace Tests\Unit\Mqtt;

use App\Enums\AccessLogStatus;
use App\Enums\DeviceCommandAction;
use App\Models\AccessLog;
use App\Mqtt\DeviceCommand;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceCommandTest extends TestCase
{
    /** 2026-10-11 08:31:04 UTC as Unix epoch seconds. */
    private const DECIDED_AT_EPOCH = 1791707464;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mqtt.command_ttl' => 10]);
    }

    public function test_an_approved_log_becomes_an_open_command_on_its_device_topic(): void
    {
        $command = DeviceCommand::fromAccessLog($this->decidedLog(AccessLogStatus::Approved));

        $this->assertSame('parkir/7/command', $command->topic());
        // assertSame on the decoded array also pins the JSON types: an
        // integer access_log_id and an integer epoch issued_at, which is
        // what the ESP32 firmware compares against its NTP clock.
        $this->assertSame([
            'action' => 'open',
            'access_log_id' => 42,
            'issued_at' => self::DECIDED_AT_EPOCH,
        ], json_decode($command->payload(), true));
    }

    public function test_a_denied_log_becomes_a_deny_command(): void
    {
        $command = DeviceCommand::fromAccessLog($this->decidedLog(AccessLogStatus::Denied));

        $this->assertSame(DeviceCommandAction::Deny, $command->action);
        $this->assertSame('deny', json_decode($command->payload(), true)['action']);
    }

    #[DataProvider('undecidedStatuses')]
    public function test_a_log_without_a_final_decision_has_no_command(AccessLogStatus $status): void
    {
        $this->expectException(InvalidArgumentException::class);

        DeviceCommand::fromAccessLog($this->decidedLog($status));
    }

    public static function undecidedStatuses(): array
    {
        return [
            'pending' => [AccessLogStatus::Pending],
            'expired' => [AccessLogStatus::Expired],
        ];
    }

    public function test_a_decided_log_without_a_decision_time_has_no_command(): void
    {
        $log = $this->decidedLog(AccessLogStatus::Approved);
        $log->processed_at = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access log [42] has no decision time');

        DeviceCommand::fromAccessLog($log);
    }

    public function test_a_command_goes_stale_exactly_ttl_seconds_after_it_was_issued(): void
    {
        $command = DeviceCommand::fromAccessLog($this->decidedLog(AccessLogStatus::Approved));

        $this->travelTo(Carbon::createFromTimestamp(self::DECIDED_AT_EPOCH + 9));
        $this->assertFalse($command->isStale());

        $this->travelTo(Carbon::createFromTimestamp(self::DECIDED_AT_EPOCH + 10));
        $this->assertTrue($command->isStale());
    }

    public function test_the_ttl_is_read_from_config(): void
    {
        // A string, because that's how an env value always arrives.
        config(['mqtt.command_ttl' => '25']);

        $command = DeviceCommand::fromAccessLog($this->decidedLog(AccessLogStatus::Approved));

        $this->assertSame(self::DECIDED_AT_EPOCH + 25, $command->staleAt());
    }

    /**
     * An in-memory access log (never saved), so these stay pure unit tests.
     */
    private function decidedLog(AccessLogStatus $status): AccessLog
    {
        return (new AccessLog)->forceFill([
            'id' => 42,
            'device_id' => 7,
            'status' => $status,
            'processed_at' => Carbon::createFromTimestamp(self::DECIDED_AT_EPOCH),
        ]);
    }
}
