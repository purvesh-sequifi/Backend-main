<?php

declare(strict_types=1);

namespace App\Core\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Trait DefinerSafeTrait
 * 
 * Provides methods for creating database objects without DEFINER issues.
 * Use this trait in migrations to ensure all triggers, views, and procedures
 * are created with SQL SECURITY INVOKER instead of DEFINER clauses.
 */
trait DefinerSafeTrait
{
    /**
     * Create a trigger without DEFINER clause
     */
    protected function createTriggerSafe(string $name, string $table, string $timing, string $event, string $statement): void
    {
        // Drop existing trigger if it exists
        DB::unprepared("DROP TRIGGER IF EXISTS `{$name}`");
        
        // Create trigger without DEFINER clause
        $sql = "CREATE TRIGGER `{$name}` {$timing} {$event} ON `{$table}` FOR EACH ROW {$statement}";
        
        $this->validateNoDefiner($sql);
        DB::unprepared($sql);
        
        $this->info("✅ Created trigger: {$name}");
    }

    /**
     * Create a view with SQL SECURITY INVOKER
     */
    protected function createViewSafe(string $name, string $selectStatement): void
    {
        // Drop existing view if it exists
        DB::unprepared("DROP VIEW IF EXISTS `{$name}`");
        
        // Create view with SQL SECURITY INVOKER
        $sql = "CREATE SQL SECURITY INVOKER VIEW `{$name}` AS {$selectStatement}";
        
        $this->validateNoDefiner($sql);
        DB::unprepared($sql);
        
        $this->info("✅ Created view: {$name}");
    }

    /**
     * Create a stored procedure with SQL SECURITY INVOKER
     */
    protected function createProcedureSafe(string $name, string $parameters, string $body): void
    {
        // Drop existing procedure if it exists
        DB::unprepared("DROP PROCEDURE IF EXISTS `{$name}`");
        
        // Create procedure with SQL SECURITY INVOKER
        $sql = "CREATE PROCEDURE `{$name}`({$parameters}) SQL SECURITY INVOKER {$body}";
        
        $this->validateNoDefiner($sql);
        DB::unprepared($sql);
        
        $this->info("✅ Created procedure: {$name}");
    }

    /**
     * Create a function with SQL SECURITY INVOKER
     */
    protected function createFunctionSafe(string $name, string $parameters, string $returns, string $body): void
    {
        // Drop existing function if it exists
        DB::unprepared("DROP FUNCTION IF EXISTS `{$name}`");
        
        // Create function with SQL SECURITY INVOKER
        $sql = "CREATE FUNCTION `{$name}`({$parameters}) RETURNS {$returns} SQL SECURITY INVOKER {$body}";
        
        $this->validateNoDefiner($sql);
        DB::unprepared($sql);
        
        $this->info("✅ Created function: {$name}");
    }

    /**
     * Validate that SQL doesn't contain DEFINER clause
     */
    protected function validateNoDefiner(string $sql): void
    {
        if (preg_match('/DEFINER\s*=\s*[^\\s]+/i', $sql)) {
            throw new \InvalidArgumentException("SQL contains DEFINER clause. Use SQL SECURITY INVOKER instead: {$sql}");
        }
    }

    /**
     * Get current database username for logging
     */
    protected function getCurrentDatabaseUser(): string
    {
        $result = DB::select('SELECT CURRENT_USER() as current_user');
        return $result[0]->current_user ?? 'unknown';
    }

    /**
     * Log DEFINER-safe operation
     */
    protected function logDefinerSafeOperation(string $operation, string $objectName): void
    {
        $user = $this->getCurrentDatabaseUser();
        $this->info("🔒 DEFINER-safe {$operation}: {$objectName} (Current user: {$user})");
    }
}
