<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create log table to track DEFINER fixes (only if it doesn't exist)
        if (!Schema::hasTable('definer_fix_log')) {
            Schema::create('definer_fix_log', function (Blueprint $table) {
                $table->id();
                $table->string('object_type', 50); // trigger, view, procedure, function
                $table->string('object_name', 255);
                $table->string('table_name', 255)->nullable();
                $table->string('old_definer', 255)->nullable();
                $table->string('new_definer', 255)->nullable();
                $table->text('old_sql')->nullable();
                $table->text('new_sql')->nullable();
                $table->string('status', 50); // success, failed, skipped
                $table->text('error_message')->nullable();
                $table->timestamp('fixed_at');
                $table->timestamps();
                
                $table->index(['object_type', 'object_name']);
                $table->index('fixed_at');
            });
        }

        // Run the DEFINER fix command
        echo "🔧 Running DEFINER fix command...\n";
        
        try {
            // Call the fix command with force and backup options
            $exitCode = Artisan::call('db:fix-definers', [
                '--force' => true,
                '--backup' => true
            ]);
            
            if ($exitCode === 0) {
                echo "✅ DEFINER issues fixed successfully!\n";
                
                // Log this migration run
                DB::table('definer_fix_log')->insert([
                    'object_type' => 'migration',
                    'object_name' => '2025_11_28_070712_fix_all_database_definers_permanently',
                    'table_name' => null,
                    'old_definer' => 'various',
                    'new_definer' => config('database.connections.mysql.username', 'current_user'),
                    'old_sql' => 'Migration run',
                    'new_sql' => 'DEFINER fix applied',
                    'status' => 'success',
                    'error_message' => null,
                    'fixed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
            } else {
                echo "❌ DEFINER fix command failed with exit code: {$exitCode}\n";
                throw new \Exception("DEFINER fix command failed");
            }
            
        } catch (\Exception $e) {
            echo "❌ Error running DEFINER fix: " . $e->getMessage() . "\n";
            
            // Log the error (only if table exists)
            if (Schema::hasTable('definer_fix_log')) {
                DB::table('definer_fix_log')->insert([
                    'object_type' => 'migration',
                    'object_name' => '2025_11_28_070712_fix_all_database_definers_permanently',
                    'table_name' => null,
                    'old_definer' => null,
                    'new_definer' => null,
                    'old_sql' => 'Migration run',
                    'new_sql' => null,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'fixed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Don't throw the exception - let the migration continue
            echo "⚠️  Migration continues - DEFINER fix can be run manually with: php artisan db:fix-definers --force\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('definer_fix_log');
        
        echo "⚠️  Note: DEFINER changes cannot be automatically reversed.\n";
        echo "   If you need to restore DEFINER values, use the backup created during the fix.\n";
    }
};
