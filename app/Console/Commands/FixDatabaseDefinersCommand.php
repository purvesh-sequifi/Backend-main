<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FixDatabaseDefinersCommand extends Command
{
    protected $signature = 'db:fix-definers 
                            {--database= : Specific database name (defaults to current)}
                            {--dry-run : Show what would be changed without making changes}
                            {--force : Skip confirmation prompts}
                            {--backup : Create backup of objects before modification}';

    protected $description = 'Fix DEFINER issues in triggers, views, and stored procedures by removing DEFINER dependency';

    public function handle(): int
    {
        try {
            $database = $this->option('database') ?? $this->getCurrentDatabase();
            $dryRun = $this->option('dry-run');
            $force = $this->option('force');
            $backup = $this->option('backup');

            $this->info("🔍 Analyzing DEFINER issues in database: {$database}");
            
            $triggers = $this->getTriggersWithDefiners($database);
            $views = $this->getViewsWithDefiners($database);
            $procedures = $this->getProceduresWithDefiners($database);

            $totalObjects = $triggers->count() + $views->count() + $procedures->count();

            if ($totalObjects === 0) {
                $this->info('✅ No DEFINER issues found in the database.');
                return Command::SUCCESS;
            }

            $this->displaySummary($triggers, $views, $procedures);

            if (!$force && !$dryRun) {
                if (!$this->confirm("Do you want to proceed with fixing {$totalObjects} database objects?")) {
                    $this->info('Operation cancelled.');
                    return Command::SUCCESS;
                }
            }

            if ($backup && !$dryRun) {
                $this->createBackup($database, $triggers, $views, $procedures);
            }

            $this->fixTriggers($triggers, $dryRun);
            $this->fixViews($views, $dryRun);
            $this->fixProcedures($procedures, $dryRun);

            if ($dryRun) {
                $this->info('🔍 Dry run completed. No changes were made.');
            } else {
                $this->info('✅ All DEFINER issues have been fixed successfully!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error fixing DEFINER issues: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getCurrentDatabase(): string
    {
        $result = DB::select('SELECT DATABASE() as dbName');
        return $result[0]->dbName;
    }

    private function getTriggersWithDefiners(string $database): Collection
    {
        $triggers = DB::select("SHOW TRIGGERS FROM `{$database}`");
        
        return collect($triggers)->map(function ($trigger) {
            return [
                'name' => $trigger->Trigger,
                'table' => $trigger->Table,
                'timing' => $trigger->Timing,
                'event' => $trigger->Event,
                'statement' => $trigger->Statement,
                'definer' => $trigger->Definer,
            ];
        });
    }

    private function getViewsWithDefiners(string $database): Collection
    {
        $views = DB::select("
            SELECT TABLE_NAME as view_name, VIEW_DEFINITION, DEFINER, SECURITY_TYPE
            FROM INFORMATION_SCHEMA.VIEWS 
            WHERE TABLE_SCHEMA = ?
        ", [$database]);

        return collect($views);
    }

    private function getProceduresWithDefiners(string $database): Collection
    {
        $procedures = DB::select("
            SELECT ROUTINE_NAME as procedure_name, ROUTINE_DEFINITION, DEFINER, SECURITY_TYPE, ROUTINE_TYPE
            FROM INFORMATION_SCHEMA.ROUTINES 
            WHERE ROUTINE_SCHEMA = ?
        ", [$database]);

        return collect($procedures);
    }

    private function displaySummary(Collection $triggers, Collection $views, Collection $procedures): void
    {
        $this->line('');
        $this->info('📊 Objects with DEFINER issues:');
        $this->line('');

        if ($triggers->isNotEmpty()) {
            $this->info("🔧 Triggers ({$triggers->count()}):");
            $this->table(
                ['Name', 'Table', 'Event', 'Timing', 'Current DEFINER'],
                $triggers->map(fn($t) => [$t['name'], $t['table'], $t['event'], $t['timing'], $t['definer']])->toArray()
            );
        }

        if ($views->isNotEmpty()) {
            $this->info("👁️ Views ({$views->count()}):");
            $this->table(
                ['Name', 'Current DEFINER', 'Security Type'],
                $views->map(fn($v) => [$v->view_name, $v->DEFINER, $v->SECURITY_TYPE])->toArray()
            );
        }

        if ($procedures->isNotEmpty()) {
            $this->info("⚙️ Procedures/Functions ({$procedures->count()}):");
            $this->table(
                ['Name', 'Type', 'Current DEFINER', 'Security Type'],
                $procedures->map(fn($p) => [$p->procedure_name, $p->ROUTINE_TYPE, $p->DEFINER, $p->SECURITY_TYPE])->toArray()
            );
        }
    }

    private function createBackup(string $database, Collection $triggers, Collection $views, Collection $procedures): void
    {
        $this->info('💾 Creating backup of database objects...');
        
        $backupFile = storage_path("app/database_backup_{$database}_" . date('Y_m_d_H_i_s') . '.sql');
        $backup = "-- Database Objects Backup for {$database}\n";
        $backup .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($triggers as $trigger) {
            $backup .= "-- Trigger: {$trigger['name']}\n";
            $backup .= "DROP TRIGGER IF EXISTS `{$trigger['name']}`;\n";
            $backup .= "DELIMITER ;;\n";
            $backup .= "CREATE TRIGGER `{$trigger['name']}` {$trigger['timing']} {$trigger['event']} ON `{$trigger['table']}` FOR EACH ROW\n";
            $backup .= "{$trigger['statement']}\n;;\n";
            $backup .= "DELIMITER ;\n\n";
        }

        foreach ($views as $view) {
            $backup .= "-- View: {$view->view_name}\n";
            $backup .= "DROP VIEW IF EXISTS `{$view->view_name}`;\n";
            $backup .= "CREATE VIEW `{$view->view_name}` AS {$view->VIEW_DEFINITION};\n\n";
        }

        foreach ($procedures as $procedure) {
            $backup .= "-- {$procedure->ROUTINE_TYPE}: {$procedure->procedure_name}\n";
            $backup .= "DROP {$procedure->ROUTINE_TYPE} IF EXISTS `{$procedure->procedure_name}`;\n";
            $backup .= "DELIMITER ;;\n";
            $backup .= "{$procedure->ROUTINE_DEFINITION}\n;;\n";
            $backup .= "DELIMITER ;\n\n";
        }

        file_put_contents($backupFile, $backup);
        $this->info("✅ Backup created: {$backupFile}");
    }

    private function fixTriggers(Collection $triggers, bool $dryRun): void
    {
        if ($triggers->isEmpty()) return;

        $this->info('🔧 Fixing triggers...');

        foreach ($triggers as $trigger) {
            $this->line("  Processing trigger: {$trigger['name']}");

            if ($dryRun) {
                $this->line("    [DRY RUN] Would recreate trigger without DEFINER");
                continue;
            }

            try {
                DB::unprepared("DROP TRIGGER IF EXISTS `{$trigger['name']}`");
                $sql = "CREATE TRIGGER `{$trigger['name']}` {$trigger['timing']} {$trigger['event']} ON `{$trigger['table']}` FOR EACH ROW\n{$trigger['statement']}";
                DB::unprepared($sql);
                $this->line("    ✅ Fixed trigger: {$trigger['name']}");
            } catch (\Exception $e) {
                $this->error("    ❌ Failed to fix trigger {$trigger['name']}: " . $e->getMessage());
            }
        }
    }

    private function fixViews(Collection $views, bool $dryRun): void
    {
        if ($views->isEmpty()) return;

        $this->info('👁️ Fixing views...');

        foreach ($views as $view) {
            $this->line("  Processing view: {$view->view_name}");

            if ($dryRun) {
                $this->line("    [DRY RUN] Would recreate view with SQL SECURITY INVOKER");
                continue;
            }

            try {
                DB::unprepared("DROP VIEW IF EXISTS `{$view->view_name}`");
                $sql = "CREATE SQL SECURITY INVOKER VIEW `{$view->view_name}` AS {$view->VIEW_DEFINITION}";
                DB::unprepared($sql);
                $this->line("    ✅ Fixed view: {$view->view_name}");
            } catch (\Exception $e) {
                $this->error("    ❌ Failed to fix view {$view->view_name}: " . $e->getMessage());
            }
        }
    }

    private function fixProcedures(Collection $procedures, bool $dryRun): void
    {
        if ($procedures->isEmpty()) return;

        $this->info('⚙️ Fixing procedures and functions...');

        foreach ($procedures as $procedure) {
            $this->line("  Processing {$procedure->ROUTINE_TYPE}: {$procedure->procedure_name}");

            if ($dryRun) {
                $this->line("    [DRY RUN] Would recreate {$procedure->ROUTINE_TYPE} with SQL SECURITY INVOKER");
                continue;
            }

            try {
                DB::unprepared("DROP {$procedure->ROUTINE_TYPE} IF EXISTS `{$procedure->procedure_name}`");
                $createStatement = $this->getProcedureCreateStatement($procedure->procedure_name, $procedure->ROUTINE_TYPE);
                
                if ($createStatement) {
                    $createStatement = $this->addSecurityInvoker($createStatement);
                    DB::unprepared($createStatement);
                    $this->line("    ✅ Fixed {$procedure->ROUTINE_TYPE}: {$procedure->procedure_name}");
                } else {
                    $this->error("    ❌ Could not get CREATE statement for {$procedure->procedure_name}");
                }
            } catch (\Exception $e) {
                $this->error("    ❌ Failed to fix {$procedure->ROUTINE_TYPE} {$procedure->procedure_name}: " . $e->getMessage());
            }
        }
    }

    private function getProcedureCreateStatement(string $name, string $type): ?string
    {
        try {
            $result = DB::select("SHOW CREATE {$type} `{$name}`");
            return $result[0]->{"Create " . ucfirst(strtolower($type))} ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function addSecurityInvoker(string $createStatement): string
    {
        $createStatement = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`\s*/i', '', $createStatement);
        
        if (!preg_match('/SQL\s+SECURITY\s+(DEFINER|INVOKER)/i', $createStatement)) {
            $createStatement = preg_replace(
                '/(\)\s*)(BEGIN|RETURNS|AS)/i',
                '$1SQL SECURITY INVOKER $2',
                $createStatement
            );
        } else {
            $createStatement = preg_replace('/SQL\s+SECURITY\s+DEFINER/i', 'SQL SECURITY INVOKER', $createStatement);
        }

        return $createStatement;
    }
}
