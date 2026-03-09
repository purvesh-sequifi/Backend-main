<?php

namespace App\Console\Commands;

use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\SalesMaster;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TestLegacyLogBackfill extends Command
{
    protected $signature = 'test:legacy-log-backfill {--with-job : Also trigger the queued job via user email change}';

    protected $description = 'Seed test user and legacy records, run closer1_id backfill, and print results.';

    public function handle(): int
    {
        $this->info('Setting up test data...');

        DB::beginTransaction();
        try {
            $suffix = Str::random(6);
            $email1 = "legacy.test+{$suffix}@example.com";
            $email2 = "legacy.test+{$suffix}.new@example.com";

            // Create or get user
            $user = User::firstOrCreate(
                ['email' => $email1],
                [
                    'first_name' => 'Legacy',
                    'last_name' => 'Tester',
                    'password' => bcrypt('password'),
                    'hire_date' => Date::now()->subDays(30)->toDateString(),
                ]
            );

            // Prepare identifiers
            $pid = 'TESTPID-'.strtoupper(Str::random(8));
            $homeownerId = 'H-'.strtoupper(Str::random(6));
            $proposalId = 'P-'.strtoupper(Str::random(6));

            // Insert history row with closer1_id = null
            $history = LegacyApiRawDataHistory::create([
                'pid' => $pid,
                'homeowner_id' => $homeownerId,
                'proposal_id' => $proposalId,
                'sales_rep_email' => $email1,
                'closer1_id' => null,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);

            // Insert log row with closer1_id = null
            $log = LegacyApiRawDataHistoryLog::create([
                'pid' => $pid,
                'homeowner_id' => $homeownerId,
                'proposal_id' => $proposalId,
                'sales_rep_email' => $email1,
                'action_type' => 'success_import',
                'closer1_id' => null,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);

            DB::commit();

            $this->info("Seeded: user_id={$user->id}, history_id={$history->id}, log_id={$log->id}, pid={$pid}");

            // Option A: Run our command to backfill
            $this->info('Running sales:update-closer-data ...');
            Artisan::call('sales:update-closer-data');
            $this->line(Artisan::output());

            // Reload and display
            $history->refresh();
            $log->refresh();
            $this->table(['record', 'id', 'pid', 'sales_rep_email', 'closer1_id'], [
                ['history', $history->id, $history->pid, $history->sales_rep_email, $history->closer1_id],
                ['log', $log->id, $log->pid, $log->sales_rep_email, $log->closer1_id],
            ]);

            // Optionally test the queued job via user email change
            if ($this->option('with-job')) {
                $this->info('Triggering queued job via user email change...');
                $oldEmail = $user->email;
                $user->email = $email2; // change to trigger observer
                $user->save();

                $this->info('Running one queue job (if any)...');
                // Best-effort to run one queued job
                Artisan::call('queue:work', ['--once' => true]);
                $this->line(Artisan::output());

                // Reload log row again post-job
                $log->refresh();
                $this->info('Post-job log closer1_id: '.var_export($log->closer1_id, true));
            }

            // Check SalesMaster
            $salesMaster = SalesMaster::where('pid', $pid)->first();
            if ($salesMaster) {
                $this->info('SalesMaster found. closer1_id='.var_export($salesMaster->closer1_id, true));
            } else {
                $this->info('No SalesMaster created/updated for this pid (expected unless mapping logic created one).');
            }

            $this->info('Test complete.');

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TestLegacyLogBackfill failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->error('Error: '.$e->getMessage());

            return 1;
        }
    }
}
