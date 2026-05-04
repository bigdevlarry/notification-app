<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Services\ExternalNotificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.notification_provider.url' => 'https://webhook.site/test-uuid']);
    }

    public function test_sms_notification_is_sent_to_provider_with_correct_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'messageId' => 'msg-uuid-123',
                'status' => 'accepted',
                'timestamp' => now()->toIso8601String(),
            ], 202),
        ]);

        $notification = Notification::factory()->create([
            'channel' => NotificationChannel::Sms,
            'recipient_address' => '+905551234567',
            'status' => NotificationStatus::Pending,
        ]);

        app(ProcessNotificationJob::class, ['notificationId' => $notification->id])
            ->handle(app(ExternalNotificationProvider::class));

        Http::assertSent(fn (Request $request) => $request['to'] === '+905551234567' &&
            $request['channel'] === 'sms' &&
            $request['content'] === $notification->content
        );

        $fresh = $notification->fresh();
        $this->assertEquals(NotificationStatus::Sent, $fresh->status);
        $this->assertEquals('msg-uuid-123', $fresh->provider_message_id);
    }

    public function test_provider_failure_marks_notification_as_failed(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $notification = Notification::factory()->create([
            'channel' => NotificationChannel::Sms,
            'recipient_address' => '+905551234567',
            'status' => NotificationStatus::Pending,
        ]);

        $this->expectException(\RuntimeException::class);

        app(ProcessNotificationJob::class, ['notificationId' => $notification->id])
            ->handle(app(ExternalNotificationProvider::class));
    }
}
