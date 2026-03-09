<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JobPerformanceTracker;
use Illuminate\Console\Command;

class ShowRecentPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:show-recent {--hours=24 : Hours to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show recent performance metrics for sales recalculation jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $tracker = new JobPerformanceTracker();
        
        $this->info("📊 Recent Performance Metrics (Last {$hours} hours)");
        $this->newLine();
        
        $comparison = $tracker->getPerformanceComparison($hours);
        $recent = $comparison['recent_period'];
        
        if ($recent['total_jobs'] === 0) {
            $this->warn("No jobs found in the last {$hours} hours.");
            return Command::SUCCESS;
        }
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', $recent['total_jobs']],
                ['Total PIDs Processed', number_format($recent['total_pids'])],
                ['Average Duration', $recent['average_duration'] . ' seconds'],
                ['Average Throughput', $recent['average_throughput'] . ' PIDs/sec'],
                ['Average Success Rate', $recent['average_success_rate'] . '%'],
                ['Average Memory Usage', $recent['average_memory_usage'] . ' MB']
            ]
        );
        
        return Command::SUCCESS;
    }
}
