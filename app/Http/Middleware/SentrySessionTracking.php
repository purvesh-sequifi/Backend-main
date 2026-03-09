<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\HubInterface;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\HttpFoundation\Response;

class SentrySessionTracking
{
    protected HubInterface $sentryHub;

    public function __construct(HubInterface $sentryHub)
    {
        $this->sentryHub = $sentryHub;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Start a transaction for session tracking
        $context = new TransactionContext;
        $context->setName($request->method().' '.$request->path());
        $context->setOp('http.request');

        $transaction = $this->sentryHub->startTransaction($context);
        $this->sentryHub->setSpan($transaction);

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            // Capture exception in Sentry
            app('sentry')->captureException($exception);
            throw $exception;
        } finally {
            $transaction->finish(); // Ensure transaction is closed
        }

        return $response;
    }
}
