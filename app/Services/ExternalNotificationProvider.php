<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ExternalNotificationProvider
{
    public function send(string $to, string $channel, string $content): string
    {
        $response = Http::post(config('services.notification_provider.url'), [
            'to' => $to,
            'channel' => $channel,
            'content' => $content,
        ]);

        if ($response->status() !== 202) {
            throw new \RuntimeException(
                "Provider rejected the request with status {$response->status()}."
            );
        }

        return $response->json('messageId');
    }
}
