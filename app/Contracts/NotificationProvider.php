<?php

namespace App\Contracts;

interface NotificationProvider
{
    public function send(string $to, string $channel, string $content): string;
}
