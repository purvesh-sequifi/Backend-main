<?php

namespace App\Console\Commands;

use App\Services\ClickHouseConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ClickHouseHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clickhouse:heartbeat 
                            {--max-retries=7 : Maximum number of retry attempts}
                            {--initial-timeout=120 : Initial timeout in seconds}
                            {--deep-sleep-mode : Use extended timeout for deep sleep wake-up}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a heartbeat query to ClickHouse to keep the connection alive';

    /**
     * Path to the heartbeat log file
     *
     * @var string
     */
    protected $logFile;

    /**
     * Constructor to initialize the log file path
     */
    public function __construct()
    {
        parent::__construct();
        $this->logFile = storage_path('logs/clickhouse-heartbeat.log');
    }

    /**
     * Write a message to the heartbeat log file
     */
    protected function writeToLog(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}".PHP_EOL;

        // Create directory if it doesn't exist
        $directory = dirname($this->logFile);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::append($this->logFile, $logMessage);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $maxRetries = (int) $this->option('max-retries');
        $initialTimeout = (int) $this->option('initial-timeout');
        $deepSleepMode = $this->option('deep-sleep-mode');
        $verbose = $this->getOutput()->isVerbose();

        $message = 'Starting ClickHouse heartbeat...';
        $this->info($message);
        $this->writeToLog($message);

        $configMessage = "Configuration: max-retries={$maxRetries}, initial-timeout={$initialTimeout}s, deep-sleep-mode=".($deepSleepMode ? 'true' : 'false');
        if ($verbose) {
            $this->info($configMessage);
        }
        $this->writeToLog($configMessage);

        try {
            $startTime = microtime(true);

            $success = false;
            if ($deepSleepMode) {
                // Use the specialized deep sleep wake-up procedure
                $deepSleepMessage = 'Using deep sleep wake-up procedure with extended timeouts...';
                $this->info($deepSleepMessage);
                $this->writeToLog($deepSleepMessage);
                $success = ClickHouseConnectionService::wakeUpDeepSleepingInstance($maxRetries, $initialTimeout);
            } else {
                // Use regular ping with configurable retries and timeout
                $success = ClickHouseConnectionService::ping($maxRetries, $initialTimeout, $verbose);
            }

            $duration = round(microtime(true) - $startTime, 2);

            if ($success) {
                $successMessage = "ClickHouse heartbeat successful (took {$duration}s)";
                $this->info($successMessage);
                $this->writeToLog($successMessage);

                Log::info('[ClickHouse Heartbeat] Heartbeat successful', [
                    'duration' => $duration,
                    'max_retries' => $maxRetries,
                    'initial_timeout' => $initialTimeout,
                    'deep_sleep_mode' => $deepSleepMode,
                ]);

                return 0;
            } else {
                $failureMessage = "ClickHouse heartbeat failed after {$maxRetries} attempts (took {$duration}s)";
                $this->error($failureMessage);
                $this->writeToLog("ERROR: {$failureMessage}");

                Log::error("[ClickHouse Heartbeat] Heartbeat failed after {$maxRetries} attempts", [
                    'duration' => $duration,
                    'max_retries' => $maxRetries,
                    'initial_timeout' => $initialTimeout,
                    'deep_sleep_mode' => $deepSleepMode,
                ]);

                $tips = [
                    'Troubleshooting tips:',
                    '1. Check that your ClickHouse connection details are correct in .env',
                    '2. Verify that the ClickHouse service is running and accessible',
                    "3. Try increasing --initial-timeout (current: {$initialTimeout}s)",
                    '4. Try using --deep-sleep-mode for extreme timeout and retry settings',
                    '5. Check network connectivity between your app and ClickHouse',
                ];

                $this->info("\n".implode("\n", $tips));
                $this->writeToLog(implode("\n", $tips));

                return 1;
            }
        } catch (\Exception $e) {
            $errorMessage = 'Command failed: '.$e->getMessage();
            $this->error($errorMessage);
            $this->writeToLog("EXCEPTION: {$errorMessage}");

            Log::error('[ClickHouse Heartbeat] Command failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            return 1;
        }
    }
}
