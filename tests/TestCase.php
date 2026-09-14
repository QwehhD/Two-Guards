<?php

namespace Tests;

use App\Services\MqttPublisherService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bound for every test so that a request which happens to trigger an
     * MQTT publish (e.g. approve/reject, Tahap 9) never opens a real
     * socket or depends on a broker being reachable. Tests that actually
     * exercise MqttPublisherService (tests/Unit/Services/MqttPublisherServiceTest)
     * construct it directly instead of resolving it from the container,
     * so this binding never gets in their way.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(MqttPublisherService::class, Mockery::mock(MqttPublisherService::class)->shouldIgnoreMissing());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
