<?php

return [
    'rate_limits' => [
        'mail' => env('RATE_LIMIT_MAIL', 100),
        'sms' => env('RATE_LIMIT_SMS', 100),
        'push' => env('RATE_LIMIT_PUSH', 100),
    ],
];
