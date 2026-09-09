<?php

namespace Tests\Feature;

use App\Enums\AccessLogStatus;
use App\Enums\DeviceMode;
use App\Enums\UserRole;
use App\Events\AccessLogCreated;
use App\Events\AccessLogResolved;
use App\Events\PortalStatusUpdated;
use App\Events\RecentActivityUpdated;
use App\Models\AccessLog;
use App\Models\Device;
use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Covers the Tahap 8 real-time wiring: which of the four broadcast events
 * fire under which conditions. The WebSocket delivery itself (Reverb ->
 * Echo -> the browser) is out of scope here — see the manual multi-tab
 * verification steps in the project notes for that; phpunit.xml already
 * points the testing environment at BROADCAST_CONNECTION=null and
 * QUEUE_CONNECTION=sync, so every dispatch below runs synchronously and
 * safely with no real network call.
 */
class AccessLogBroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_mode_scan_dispatches_access_log_created_and_recent_activity_updated(): void
    {
        Event::fake([AccessLogCreated::class, RecentActivityUpdated::class]);

        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Manual]);
        $card = RfidCard::factory()->create();

        $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
            'uid' => $card->uid,
        ])->assertCreated();

        Event::assertDispatched(AccessLogCreated::class, fn ($event) => $event->accessLog->status === AccessLogStatus::Pending
            && $event->accessLog->device_id === $device->id);
        Event::assertDispatched(RecentActivityUpdated::class);
    }

    public function test_auto_mode_scan_dispatches_recent_activity_updated_but_not_access_log_created(): void
    {
        Event::fake([AccessLogCreated::class, RecentActivityUpdated::class]);

        $user = User::factory()->create(['role' => UserRole::Karyawan]);
        $device = Device::factory()->create(['mode' => DeviceMode::Auto]);
        $card = RfidCard::factory()->create();

        $this->actingAs($user)->postJson('/api/access-logs/simulate-scan', [
            'device_id' => $device->id,
            'uid' => $card->uid,
        ])->assertCreated();

        // The Approvals page's private feed only cares about scans that
        // need a human decision — an auto-mode scan resolves itself the
        // instant it's recorded, so it must never reach that feed.
        Event::assertNotDispatched(AccessLogCreated::class);
        Event::assertDispatched(RecentActivityUpdated::class);
    }

    public function test_approving_a_pending_scan_dispatches_access_log_resolved(): void
    {
        Event::fake([AccessLogResolved::class]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->pending()->create();

        $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve")->assertOk();

        Event::assertDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $log->id
            && $event->accessLog->status === AccessLogStatus::Approved);
    }

    public function test_rejecting_a_pending_scan_dispatches_access_log_resolved(): void
    {
        Event::fake([AccessLogResolved::class]);

        $karyawan = User::factory()->create(['role' => UserRole::Karyawan]);
        $log = AccessLog::factory()->pending()->create();

        $this->actingAs($karyawan)->postJson("/api/access-logs/{$log->id}/reject")->assertOk();

        Event::assertDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $log->id
            && $event->accessLog->status === AccessLogStatus::Denied);
    }

    public function test_self_healed_expiry_on_approve_still_dispatches_access_log_resolved(): void
    {
        Event::fake([AccessLogResolved::class]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 5),
        ]);

        // The actor gets a 422 (their approval was too late)...
        $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve")->assertStatus(422);

        // ...but every other client watching the Approvals page still
        // needs to see this item disappear, since it silently flipped to
        // Expired under them.
        Event::assertDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $log->id
            && $event->accessLog->status === AccessLogStatus::Expired);
    }

    public function test_approving_an_already_processed_scan_does_not_dispatch_access_log_resolved(): void
    {
        Event::fake([AccessLogResolved::class]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->pending()->create();
        $log->update(['status' => AccessLogStatus::Approved, 'processed_by' => $admin->id]);

        $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve")->assertStatus(422);

        // Nothing actually changed server-side, so there is nothing new
        // for any client's Approvals page to react to.
        Event::assertNotDispatched(AccessLogResolved::class);
    }

    public function test_expire_pending_command_dispatches_access_log_resolved_per_expired_log(): void
    {
        Event::fake([AccessLogResolved::class]);

        $stale1 = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 5),
        ]);
        $stale2 = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 10),
        ]);
        $fresh = AccessLog::factory()->pending()->create([
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS - 5),
        ]);
        $alreadyApproved = AccessLog::factory()->create([
            'status' => AccessLogStatus::Approved,
            'scanned_at' => now()->subSeconds(AccessLog::PENDING_TIMEOUT_SECONDS + 5),
        ]);

        $this->artisan('access-logs:expire-pending')->assertSuccessful();

        // One event per row the sweep actually changed — not a single
        // event for the whole batch — so every connected Approvals page
        // can drop exactly the items that expired.
        Event::assertDispatchedTimes(AccessLogResolved::class, 2);
        Event::assertDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $stale1->id);
        Event::assertDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $stale2->id);
        Event::assertNotDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $fresh->id);
        Event::assertNotDispatched(AccessLogResolved::class, fn ($event) => $event->accessLog->id === $alreadyApproved->id);
    }

    public function test_resolving_a_scan_triggers_public_portal_and_recent_activity_updates(): void
    {
        // AccessLogResolved is deliberately left un-faked here so its real,
        // auto-discovered listener (BroadcastPublicLandingUpdates) runs —
        // only the two public events it dispatches are faked, to confirm
        // that listener is actually what connects a resolution to the
        // public landing page refreshing.
        Event::fake([PortalStatusUpdated::class, RecentActivityUpdated::class]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $log = AccessLog::factory()->pending()->create();

        $this->actingAs($admin)->postJson("/api/access-logs/{$log->id}/approve")->assertOk();

        Event::assertDispatched(PortalStatusUpdated::class);
        Event::assertDispatched(RecentActivityUpdated::class);
    }

    public function test_access_log_events_broadcast_on_the_private_channel(): void
    {
        $log = AccessLog::factory()->pending()->create();

        $createdChannels = (new AccessLogCreated($log))->broadcastOn();
        $resolvedChannels = (new AccessLogResolved($log))->broadcastOn();

        $this->assertCount(1, $createdChannels);
        $this->assertInstanceOf(PrivateChannel::class, $createdChannels[0]);
        $this->assertSame('private-access-logs', $createdChannels[0]->name);

        $this->assertCount(1, $resolvedChannels);
        $this->assertInstanceOf(PrivateChannel::class, $resolvedChannels[0]);
        $this->assertSame('private-access-logs', $resolvedChannels[0]->name);
    }

    public function test_public_landing_events_broadcast_on_the_public_channel(): void
    {
        $portalChannels = (new PortalStatusUpdated)->broadcastOn();
        $activityChannels = (new RecentActivityUpdated)->broadcastOn();

        $this->assertCount(1, $portalChannels);
        $this->assertInstanceOf(Channel::class, $portalChannels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $portalChannels[0]);
        $this->assertSame('landing', $portalChannels[0]->name);

        $this->assertCount(1, $activityChannels);
        $this->assertInstanceOf(Channel::class, $activityChannels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $activityChannels[0]);
        $this->assertSame('landing', $activityChannels[0]->name);
    }
}
