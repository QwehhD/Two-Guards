<?php

namespace Tests;

use App\Services\MqttPublisherService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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

    /**
     * Fortify's 2FA, passkey and password-confirmation screens were built for
     * the old Inertia frontend. They are deferred, not dropped: tests in the
     * `deferred-security` group stay as a reminder (run them with
     * `php artisan test --group=deferred-security`) until those features
     * come back as API endpoints in a follow-up task.
     */
    protected function skipDeferredSecurityFeature(): void
    {
        $this->markTestSkipped('Ditunda: 2FA, passkey, dan konfirmasi password Fortify akan dibuat ulang sebagai API di task terpisah.');
    }
}
