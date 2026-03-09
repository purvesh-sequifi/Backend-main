<?php

namespace App\Console\Commands;

use App\Http\Services\ExternalApiService;
use Illuminate\Console\Command;

class CleanupExpiredApiTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'external-api:cleanup-expired-tokens 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--show-stats : Display comprehensive token statistics after cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired external API tokens and optionally display usage statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $externalApiService = app(ExternalApiService::class);

        // Show current statistics before cleanup
        $this->displayCurrentStatistics($externalApiService);

        if ($this->option('dry-run')) {
            $expiredTokensCount = \App\Models\ExternalApiToken::expired()->count();
            $this->warn("DRY RUN: Would delete {$expiredTokensCount} expired tokens");

            return Command::SUCCESS;
        }

        // Perform actual cleanup
        $deletedTokensCount = $externalApiService->cleanupExpiredTokens();

        if ($deletedTokensCount > 0) {
            $this->info("✅ Successfully cleaned up {$deletedTokensCount} expired tokens");
        } else {
            $this->info('✅ No expired tokens found to clean up');
        }

        // Show updated statistics if requested
        if ($this->option('show-stats')) {
            $this->line(''); // Add spacing
            $this->displayCurrentStatistics($externalApiService, 'Updated ');
        }

        return Command::SUCCESS;
    }

    /**
     * Display current token statistics in a formatted table
     */
    private function displayCurrentStatistics(ExternalApiService $externalApiService, string $titlePrefix = ''): void
    {
        $usageStatistics = $externalApiService->getTokenUsageStatistics();

        $this->line("{$titlePrefix}Token Statistics:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Active Tokens', $usageStatistics['total_active_tokens']],
                ['Expired Tokens', $usageStatistics['total_expired_tokens']],
                ['Expiring Soon (30 days)', $usageStatistics['tokens_expiring_soon']],
                ['Used in Last 24h', $usageStatistics['tokens_used_last_24h']],
                ['Used in Last 7 days', $usageStatistics['tokens_used_last_7d']],
                ['Never Used', $usageStatistics['tokens_never_used']],
                ['Average Token Age (days)', $usageStatistics['average_token_age_days']],
            ]
        );
    }
}
