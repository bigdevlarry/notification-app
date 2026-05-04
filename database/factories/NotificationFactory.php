<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'recipient_id'    => User::factory(),
            'idempotency_key' => $this->faker->uuid(),
            'channel'         => $this->faker->randomElement([NotificationChannel::Mail, NotificationChannel::Sms, NotificationChannel::Push]),
            'content'         => $this->faker->sentence(),
            'priority'        => $this->faker->randomElement(NotificationPriority::cases()),
            'status'          => NotificationStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status'  => NotificationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status'         => NotificationStatus::Failed,
            'failed_at'      => now(),
            'failure_reason' => $this->faker->sentence(),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'read_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => NotificationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
