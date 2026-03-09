<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\JobPerformanceTracker;
use Illuminate\Console\Command;

class ComparePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:compare {--hours=24 : Hours for each comparison period}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare performance between two time periods';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $tracker = new JobPerformanceTracker();
        
        $this->info("🔄 Performance Comparison (Last {$hours}h vs Previous {$hours}h)");
        $this->newLine();
        
        $comparison = $tracker->getPerformanceComparison($hours);
        $recent = $comparison['recent_period'];
        $previous = $comparison['previous_period'];
        $improvement = $comparison['improvement'];
        
        if ($recent['total_jobs'] === 0 && $previous['total_jobs'] === 0) {
            $this->warn("No jobs found in either time period.");
            return Command::SUCCESS;
        }
        
        $this->table(
            ['Metric', 'Recent Period', 'Previous Period', 'Change'],
            [
                [
                    'Total Jobs',
                    $recent['total_jobs'],
                    $previous['total_jobs'],
                    $this->formatChange($recent['total_jobs'] - $previous['total_jobs'])
                ],
                [
                    'Total PIDs',
                    number_format($recent['total_pids']),
                    number_format($previous['total_pids']),
                    $this->formatChange($recent['total_pids'] - $previous['total_pids'])
                ],
                [
                    'Avg Duration (sec)',
                    $recent['average_duration'],
                    $previous['average_duration'],
                    $this->formatPercentageChange($improvement['average_duration']) . '%'
                ],
                [
                    'Avg Throughput (PIDs/sec)',
                    $recent['average_throughput'],
                    $previous['average_throughput'],
                    $this->formatPercentageChange($improvement['average_throughput']) . '%'
                ],
                [
                    'Avg Success Rate (%)',
                    $recent['average_success_rate'],
                    $previous['average_success_rate'],
                    $this->formatPercentageChange($improvement['average_success_rate']) . '%'
                ],
                [
                    'Avg Memory Usage (MB)',
                    $recent['average_memory_usage'],
                    $previous['average_memory_usage'],
                    $this->formatChange($recent['average_memory_usage'] - $previous['average_memory_usage']) . ' MB'
                ]
            ]
        );
        
        $this->newLine();
        $this->info("📈 Performance Summary:");
        
        if ($improvement['average_throughput'] > 0) {
            $this->info("✅ Throughput improved by {$improvement['average_throughput']}%");
        } elseif ($improvement['average_throughput'] < 0) {
            $this->warn("⚠️ Throughput decreased by " . abs($improvement['average_throughput']) . "%");
        }
        
        if ($improvement['average_duration'] < 0) {
            $this->info("✅ Duration improved (faster) by " . abs($improvement['average_duration']) . "%");
        } elseif ($improvement['average_duration'] > 0) {
            $this->warn("⚠️ Duration increased (slower) by {$improvement['average_duration']}%");
        }
        
        return Command::SUCCESS;
    }
    
    private function formatChange(float $change): string
    {
        if ($change > 0) {
            return "+{$change}";
        } elseif ($change < 0) {
            return (string) $change;
        }
        return "0";
    }
    
    private function formatPercentageChange(float $change): string
    {
        if ($change > 0) {
            return "+{$change}";
        }
        return (string) $change;
    }
}
