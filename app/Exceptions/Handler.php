<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (
                $e instanceof \Illuminate\Database\QueryException &&
                (preg_match('/MySQL server has gone away/', $e->getMessage()) ||
                 preg_match('/SQLSTATE\[HY000\] \[2002\]/', $e->getMessage()) ||
                 preg_match('/SQLSTATE\[HY000\] \[2003\]/', $e->getMessage()))
            ) {
                return false;
            }
        });
        
        // Handle POST size exceeded exception with proper error message
        $this->renderable(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            $postMaxSize = ini_get('post_max_size');
            $contentLength = $request->header('Content-Length', 0);
            $sizeMB = round($contentLength / 1024 / 1024, 2);
            
            \Log::warning('PostTooLargeException caught', [
                'content_length' => $contentLength,
                'content_length_mb' => $sizeMB,
                'post_max_size' => $postMaxSize,
                'url' => $request->url(),
            ]);
            
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'ApiName' => 'post-too-large',
                    'message' => "The uploaded file is too large ({$sizeMB}MB). The server limit is {$postMaxSize}. Please upload a smaller file or contact support.",
                ], 413);
            }
            
            // For non-API requests, use default handling
            return null;
        });
    }

    // Inside the report method
    public function report(Throwable $exception)
    {
        // Skip Sentry reporting for MySQL connection errors
        if (
            $exception instanceof \Illuminate\Database\QueryException &&
            (preg_match('/SQLSTATE\[HY000\] \[2002\]/', $exception->getMessage()) ||
             preg_match('/SQLSTATE\[HY000\] \[2003\]/', $exception->getMessage()) ||
             preg_match('/MySQL server has gone away/', $exception->getMessage()) ||
             preg_match('/MySQL server is down/', $exception->getMessage()))
        ) {
            return;
        }

        // Everything else gets reported as normal
        parent::report($exception);

        // Send to Sentry if it should be reported
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

    }
}
