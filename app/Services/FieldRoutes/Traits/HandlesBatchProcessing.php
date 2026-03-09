<?php

namespace App\Services\FieldRoutes\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait HandlesBatchProcessing
{
    /**
     * Process records in batches
     */
    protected function processBatch(Collection $records, callable $callback, ?int $batchSize = null): array
    {
        $batchSize = $batchSize ?? config('fieldroutes.sync.batch_size', 100);
        $results = [];
        $errors = [];
        $processed = 0;

        // Process records in chunks
        $records->chunk($batchSize)->each(function ($batch) use ($callback, &$results, &$errors, &$processed) {
            try {
                // Process the batch
                $batchResults = $callback($batch);
                $results = array_merge($results, $batchResults);
                $processed += count($batch);

                // Log progress
                Log::channel(config('fieldroutes.logging.channel'))->info(
                    'Processed batch',
                    [
                        'processed' => $processed,
                        'batch_size' => count($batch),
                        'success' => count($batchResults),
                    ]
                );
            } catch (\Exception $e) {
                // Log batch error
                Log::channel(config('fieldroutes.logging.channel'))->error(
                    'Batch processing error',
                    [
                        'error' => $e->getMessage(),
                        'batch_size' => count($batch),
                    ]
                );
                $errors[] = [
                    'batch_size' => count($batch),
                    'error' => $e->getMessage(),
                ];
            }
        });

        return [
            'results' => $results,
            'errors' => $errors,
            'processed' => $processed,
        ];
    }

    /**
     * Retry failed operations
     */
    protected function retryFailedOperations(array $failedOperations, callable $callback, ?int $maxAttempts = null): array
    {
        $maxAttempts = $maxAttempts ?? config('fieldroutes.sync.retry_attempts', 3);
        $retryDelay = config('fieldroutes.sync.retry_delay', 300);
        $results = [];
        $remainingErrors = [];

        foreach ($failedOperations as $operation) {
            $attempts = 0;
            $success = false;

            while ($attempts < $maxAttempts && ! $success) {
                try {
                    if ($attempts > 0) {
                        // Wait before retrying
                        sleep($retryDelay);
                    }

                    $result = $callback($operation);
                    $results[] = $result;
                    $success = true;

                    Log::channel(config('fieldroutes.logging.channel'))->info(
                        'Retry successful',
                        [
                            'attempt' => $attempts + 1,
                            'operation' => $operation,
                        ]
                    );
                } catch (\Exception $e) {
                    $attempts++;

                    Log::channel(config('fieldroutes.logging.channel'))->warning(
                        'Retry failed',
                        [
                            'attempt' => $attempts,
                            'max_attempts' => $maxAttempts,
                            'error' => $e->getMessage(),
                            'operation' => $operation,
                        ]
                    );

                    if ($attempts >= $maxAttempts) {
                        $remainingErrors[] = [
                            'operation' => $operation,
                            'error' => $e->getMessage(),
                            'attempts' => $attempts,
                        ];
                    }
                }
            }
        }

        return [
            'results' => $results,
            'remaining_errors' => $remainingErrors,
        ];
    }
}
