<?php

namespace App\Services\Concerns;

trait ResolvesCorrelationId
{
    private function correlationId(): ?string
    {
        return request()->attributes->get('correlation_id');
    }
}
