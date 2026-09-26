<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notification scope isolation.
 *
 * The user_notifications table holds THREE actor surfaces keyed by
 * `scope`: tourist, business, platform. Every read on the table must
 * filter by (user_id, scope). These tests prove that contract holds
 * at the service level — the layer every observer and page goes
 * through.
 */
class UserNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_defaults_missing_scope_to_platform(): void
    {
        $user    = User::factory()->create();
        $service = app(UserNotificationService::class);

        $notification = $service->notify($user, [
            'type'    => 'system',
            'title'   => 'Welcome',
            'message' => 'Hello there.',
        ]);

        $this->assertNotNull($notification);
        $this->assertSame(UserNotification::SCOPE_PLATFORM, $notification->scope);
    }

    public function test_unread_count_is_isolated_per_scope(): void
    {
        $user    = User::factory()->create();
        $service = app(UserNotificationService::class);

        $service->notify($user, [
            'scope'   => UserNotification::SCOPE_TOURIST,
            'type'    => 'booking',
            'title'   => 'Trip confirmed',
            'message' => 'See you soon.',
        ]);

        $service->notify($user, [
            'scope'   => UserNotification::SCOPE_BUSINESS,
            'type'    => 'booking',
            'title'   => 'New booking',
            'message' => 'Someone booked.',
        ]);

        $this->assertSame(1, $service->unreadCount($user, UserNotification::SCOPE_TOURIST));
        $this->assertSame(1, $service->unreadCount($user, UserNotification::SCOPE_BUSINESS));
        $this->assertSame(0, $service->unreadCount($user, UserNotification::SCOPE_PLATFORM));
    }

    public function test_mark_all_read_only_touches_the_given_scope(): void
    {
        $user    = User::factory()->create();
        $service = app(UserNotificationService::class);

        $service->notify($user, [
            'scope'   => UserNotification::SCOPE_TOURIST,
            'type'    => 'booking',
            'title'   => 'A',
            'message' => 'A',
        ]);

        $service->notify($user, [
            'scope'   => UserNotification::SCOPE_BUSINESS,
            'type'    => 'booking',
            'title'   => 'B',
            'message' => 'B',
        ]);

        $updated = $service->markAllRead($user, UserNotification::SCOPE_TOURIST);

        $this->assertSame(1, $updated);
        $this->assertSame(0, $service->unreadCount($user, UserNotification::SCOPE_TOURIST));
        $this->assertSame(1, $service->unreadCount($user, UserNotification::SCOPE_BUSINESS));
    }

    public function test_mark_read_refuses_notifications_owned_by_another_user(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $service = app(UserNotificationService::class);

        $notification = $service->notify($owner, [
            'scope'   => UserNotification::SCOPE_TOURIST,
            'type'    => 'booking',
            'title'   => 'X',
            'message' => 'X',
        ]);

        $result = $service->markRead($other, $notification->id);

        $this->assertFalse($result);
        $notification->refresh();
        $this->assertNull($notification->read_at);
    }
}