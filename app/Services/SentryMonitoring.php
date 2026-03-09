<?php

namespace App\Services;

use Sentry\CheckInStatus;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;

class SentryMonitoring
{
    private HubInterface $hub;

    public function __construct(Hub $hub)
    {
        $this->hub = $hub;
    }

    public function startMonitoring(string $slug): string
    {
        $client = $this->hub->getClient();
        if (! $client) {
            return '';
        }

        $context = new TransactionContext;
        $context->setName($slug);
        $context->setOp('monitoring');

        $checkIn = $this->hub->startTransaction($context);

        return $checkIn ? $checkIn->getTraceId() : '';
    }

    public function updateStatus(string $checkInId, CheckInStatus $status): void
    {
        $client = $this->hub->getClient();
        if (! $client || ! $checkInId) {
            return;
        }

        $span = $this->hub->getSpan();
        if ($span) {
            $span->setStatus($status === CheckInStatus::ok() ? SpanStatus::ok() : SpanStatus::internalError());
            $span->finish();
        }
    }
}
