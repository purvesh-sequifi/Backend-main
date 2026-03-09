<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ShowJobStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show current sales recalculation job status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("🚀 Sales Recalculation Performance Status");
        $this->newLine();

        try {
            // Get job status
            $response = Http::get('https://solarstage.api.sequifi.com/performance/job-status');
            
            if (!$response->successful()) {
                $this->error("❌ Failed to fetch job status");
                return Command::FAILURE;
            }

            $data = $response->json();
            
            if (!$data['status']) {
                $this->error("❌ API returned error: " . ($data['message'] ?? 'Unknown error'));
                return Command::FAILURE;
            }

            $summary = $data['data']['summary'];
            $queueStatus = $data['data']['queue_status'];

            // Display summary
            $this->info("📊 Job Progress Summary:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Completed Chunks', $summary['completed_chunks']],
                    ['PIDs Processed', number_format($summary['total_processed_pids'])],
                    ['Success Rate', $summary['success_rate'] . '%'],
                    ['Average Throughput', $summary['average_throughput'] . ' PIDs/sec'],
                    ['Average Duration', $summary['average_duration_seconds'] . ' seconds'],
                    ['Overall Progress', $summary['progress_percentage'] . '%'],
                    ['Remaining PIDs', number_format($summary['estimated_remaining_pids'])],
                    ['Last Job Completed', $summary['last_job_completed'] ?? 'N/A']
                ]
            );

            // Display queue status
            $this->newLine();
            $this->info("📋 Queue Status:");
            $queueData = [];
            foreach ($queueStatus as $queueName => $queue) {
                $queueData[] = [
                    $queueName,
                    $queue['pending_jobs'] ?? 'N/A',
                    $queue['status'] ?? 'unknown'
                ];
            }
            $this->table(['Queue', 'Pending Jobs', 'Status'], $queueData);

            // Show recent jobs
            if (!empty($data['data']['recent_jobs'])) {
                $this->newLine();
                $this->info("⚡ Recent Job Performance:");
                $recentJobs = array_slice($data['data']['recent_jobs'], -5); // Last 5 jobs
                $jobData = [];
                foreach ($recentJobs as $job) {
                    $jobData[] = [
                        $job['timestamp'],
                        $job['total_pids'],
                        $job['success_count'] . '/' . $job['total_pids'],
                        round($job['duration_ms'] / 1000, 1) . 's',
                        round($job['throughput'], 2) . ' PIDs/sec'
                    ];
                }
                $this->table(
                    ['Timestamp', 'PIDs', 'Success', 'Duration', 'Throughput'],
                    $jobData
                );
            }

            // Show dashboard link
            $this->newLine();
            $this->info("🌐 View full dashboard at: https://solarstage.api.sequifi.com/performance-dashboard");
            
            // Show progress bar
            $progress = min(100, max(0, $summary['progress_percentage']));
            $barLength = 50;
            $filledLength = (int)($barLength * $progress / 100);
            $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
            
            $this->newLine();
            $this->info("Progress: [{$bar}] {$progress}%");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
