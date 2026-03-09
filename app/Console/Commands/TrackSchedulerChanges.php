<?php

namespace App\Console\Commands;

use App\Notifications\SchedulerChangeAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;

class TrackSchedulerChanges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduler:track-changes {--notify-slack : Send notification to Slack} {--force : Force running in any environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Track changes to the scheduler and notify on Slack when new schedulers are added in UAT environment';

    /**
     * The path to store the snapshot.
     *
     * @var string
     */
    protected $snapshotPath = 'scheduler_snapshots';

    /**
     * The filename for the scheduler snapshot.
     *
     * @var string
     */
    protected $snapshotFilename = 'scheduler_snapshot.json';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Only proceed if we're in the UAT environment or force flag is used
        if (env('APP_ENV') != 'uat' && ! $this->option('force')) {
            $this->info('This command only runs in the UAT environment. Use --force to override.');

            return 0;
        }

        // Get current scheduler commands
        $currentSchedulers = $this->getCurrentSchedulers();

        // Check if we have a previous snapshot
        if (! Storage::exists($this->snapshotPath.'/'.$this->snapshotFilename)) {
            // If not, create the first snapshot
            $this->createSnapshot($currentSchedulers);
            $this->info('Initial scheduler snapshot created.');

            return 0;
        }

        // Get previous snapshot
        $previousSchedulers = $this->getPreviousSnapshot();

        // Compare and find new schedulers
        $newSchedulers = $this->findNewSchedulers($previousSchedulers, $currentSchedulers);

        if (count($newSchedulers) > 0) {
            $this->info(count($newSchedulers).' new scheduler(s) detected.');

            // Display new schedulers in console
            foreach ($newSchedulers as $scheduler) {
                $this->line('- '.$scheduler['command'].' ('.$scheduler['frequency'].')');
            }

            // Send Slack notification if requested
            if ($this->option('notify-slack')) {
                $this->sendSlackNotification($newSchedulers);
                $this->info('Slack notification sent.');
            }

            // Update the snapshot
            $this->createSnapshot($currentSchedulers);
            $this->info('Scheduler snapshot updated.');
        } else {
            $this->info('No new schedulers detected.');
        }

        return 0;
    }

    /**
     * Get the current scheduler commands from the Kernel file.
     */
    protected function getCurrentSchedulers(): array
    {
        $schedulers = [];

        try {
            // Use reflection to access the schedule method
            $kernel = app()->make(\App\Console\Kernel::class);
            $reflectionClass = new ReflectionClass($kernel);
            $scheduleMethod = $reflectionClass->getMethod('schedule');

            // Make the method accessible
            $scheduleMethod->setAccessible(true);

            // Get the file content
            $kernelPath = app_path('Console/Kernel.php');
            $content = File::get($kernelPath);

            // Parse commands from the file content
            preg_match_all('/\$schedule->command\(\'(.*?)\'\)(->.*?);/m', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $command = $match[1];
                $frequency = $this->parseFrequency($match[2]);

                $schedulers[] = [
                    'command' => $command,
                    'frequency' => $frequency,
                ];
            }
        } catch (\Exception $e) {
            $this->error('Error accessing scheduler: '.$e->getMessage());
        }

        return $schedulers;
    }

    /**
     * Parse the frequency from the scheduler method chain.
     */
    protected function parseFrequency(string $methodChain): string
    {
        $frequencyMap = [
            '->everyMinute(' => 'Every Minute',
            '->hourly(' => 'Hourly',
            '->daily(' => 'Daily',
            '->dailyAt(' => 'Daily At',
            '->weekly(' => 'Weekly',
            '->weeklyOn(' => 'Weekly On',
            '->monthly(' => 'Monthly',
            '->monthlyOn(' => 'Monthly On',
            '->quarterly(' => 'Quarterly',
            '->yearly(' => 'Yearly',
            '->yearlyOn(' => 'Yearly On',
            '->everyFiveMinutes(' => 'Every 5 Minutes',
            '->everyTenMinutes(' => 'Every 10 Minutes',
            '->everyFifteenMinutes(' => 'Every 15 Minutes',
            '->everyThirtyMinutes(' => 'Every 30 Minutes',
            '->everySixHours(' => 'Every 6 Hours',
            '->everyTwoHours(' => 'Every 2 Hours',
            '->everyThreeHours(' => 'Every 3 Hours',
            '->everyFourHours(' => 'Every 4 Hours',
            '->weekdays(' => 'Weekdays',
            '->weekends(' => 'Weekends',
        ];

        foreach ($frequencyMap as $method => $description) {
            if (strpos($methodChain, $method) !== false) {
                if ($method == '->dailyAt(' || $method == '->weeklyOn(' || $method == '->monthlyOn(' || $method == '->yearlyOn(') {
                    preg_match('/'.preg_quote($method, '/').'\'(.*?)\'/', $methodChain, $timeMatches);
                    if (isset($timeMatches[1])) {
                        return $description.' '.$timeMatches[1];
                    }
                }

                return $description;
            }
        }

        return 'Custom Schedule';
    }

    /**
     * Create a snapshot of the current schedulers.
     */
    protected function createSnapshot(array $schedulers): void
    {
        // Ensure the directory exists
        if (! Storage::exists($this->snapshotPath)) {
            Storage::makeDirectory($this->snapshotPath);
        }

        // Save the snapshot
        Storage::put(
            $this->snapshotPath.'/'.$this->snapshotFilename,
            json_encode($schedulers, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Get the previous snapshot.
     */
    protected function getPreviousSnapshot(): array
    {
        $content = Storage::get($this->snapshotPath.'/'.$this->snapshotFilename);

        return json_decode($content, true) ?? [];
    }

    /**
     * Find new schedulers by comparing previous and current snapshots.
     */
    protected function findNewSchedulers(array $previousSchedulers, array $currentSchedulers): array
    {
        $newSchedulers = [];

        foreach ($currentSchedulers as $current) {
            $found = false;
            foreach ($previousSchedulers as $previous) {
                if ($current['command'] === $previous['command']) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $newSchedulers[] = $current;
            }
        }

        return $newSchedulers;
    }

    /**
     * Send a Slack notification with the new schedulers.
     */
    protected function sendSlackNotification(array $newSchedulers): void
    {
        // Get the Slack webhook URL from the environment
        $slackWebhook = config('services.slack.webhook_url');

        if (! $slackWebhook) {
            $this->warn('Slack webhook URL not configured. Notification not sent.');

            return;
        }

        try {
            Notification::route('slack', $slackWebhook)
                ->notify(new SchedulerChangeAlert($newSchedulers, env('APP_ENV', 'unknown')));
        } catch (\Exception $e) {
            $this->error('Failed to send Slack notification: '.$e->getMessage());
        }
    }
}
