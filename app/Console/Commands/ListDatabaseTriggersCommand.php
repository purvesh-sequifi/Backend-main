<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ListDatabaseTriggersCommand extends Command
{
    protected $signature = 'db:list-triggers 
                            {--database= : Specific database name to check (defaults to current)}
                            {--table= : Filter by specific table name}
                            {--format=table : Output format (table|json|csv)}
                            {--detailed : Show detailed trigger information including SQL}';

    protected $description = 'List all database triggers with their details';

    public function handle(): int
    {
        try {
            $database = $this->option('database') ?? $this->getCurrentDatabase();
            $table = $this->option('table');
            $format = $this->option('format');
            $detailed = $this->option('detailed');

            $this->info("🔍 Listing triggers for database: {$database}");
            
            $triggers = $this->getTriggers($database, $table);
            
            if ($triggers->isEmpty()) {
                $this->warn('No triggers found.');
                return Command::SUCCESS;
            }

            $this->displayTriggers($triggers, $format, $detailed);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function getCurrentDatabase(): string
    {
        $result = DB::select('SELECT DATABASE() as db_name');
        return $result[0]->db_name ?? config('database.connections.mysql.database', 'unknown');
    }

    private function getTriggers(string $database, ?string $table = null): Collection
    {
        $query = "SHOW TRIGGERS";
        
        if ($table) {
            $query .= " LIKE '{$table}'";
        }
        
        $triggers = collect(DB::select($query));
        
        return $triggers->map(function ($trigger) {
            return (object) [
                'name' => $trigger->Trigger,
                'table' => $trigger->Table,
                'timing' => $trigger->Timing,
                'event' => $trigger->Event,
                'definer' => $trigger->Definer,
                'statement' => $trigger->Statement ?? null,
            ];
        });
    }

    private function displayTriggers(Collection $triggers, string $format, bool $detailed): void
    {
        switch ($format) {
            case 'json':
                $this->displayJson($triggers, $detailed);
                break;
            case 'csv':
                $this->displayCsv($triggers, $detailed);
                break;
            default:
                $this->displayTable($triggers, $detailed);
        }
    }

    private function displayTable(Collection $triggers, bool $detailed): void
    {
        $headers = ['Name', 'Table', 'Timing', 'Event', 'Definer'];
        
        if ($detailed) {
            $headers[] = 'Statement (First 100 chars)';
        }

        $rows = $triggers->map(function ($trigger) use ($detailed) {
            $row = [
                $trigger->name,
                $trigger->table,
                $trigger->timing,
                $trigger->event,
                $trigger->definer,
            ];
            
            if ($detailed && $trigger->statement) {
                $row[] = substr(str_replace(["\n", "\r"], ' ', $trigger->statement), 0, 100) . '...';
            }
            
            return $row;
        })->toArray();

        $this->table($headers, $rows);
        $this->info("Total triggers: " . $triggers->count());
    }

    private function displayJson(Collection $triggers, bool $detailed): void
    {
        $data = $triggers->map(function ($trigger) use ($detailed) {
            $result = [
                'name' => $trigger->name,
                'table' => $trigger->table,
                'timing' => $trigger->timing,
                'event' => $trigger->event,
                'definer' => $trigger->definer,
            ];
            
            if ($detailed && $trigger->statement) {
                $result['statement'] = $trigger->statement;
            }
            
            return $result;
        });

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function displayCsv(Collection $triggers, bool $detailed): void
    {
        $headers = ['Name', 'Table', 'Timing', 'Event', 'Definer'];
        
        if ($detailed) {
            $headers[] = 'Statement';
        }
        
        $this->line(implode(',', $headers));
        
        $triggers->each(function ($trigger) use ($detailed) {
            $row = [
                $trigger->name,
                $trigger->table,
                $trigger->timing,
                $trigger->event,
                $trigger->definer,
            ];
            
            if ($detailed && $trigger->statement) {
                $row[] = '"' . str_replace('"', '""', $trigger->statement) . '"';
            }
            
            $this->line(implode(',', $row));
        });
    }
}
