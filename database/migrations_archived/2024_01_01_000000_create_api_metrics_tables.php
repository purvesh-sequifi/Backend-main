<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Main metrics table (optimized for writes)
        Schema::connection('api_metrics')->create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 255)->index(); // Normalized endpoint
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->decimal('response_time_ms', 10, 2);
            $table->decimal('memory_usage_mb', 8, 4);
            $table->decimal('peak_memory_mb', 8, 4);
            $table->decimal('cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('request_size_kb', 8, 2)->default(0);
            $table->decimal('response_size_kb', 8, 2)->default(0);
            $table->timestamp('timestamp');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent_hash', 32)->nullable();
            $table->boolean('is_error')->storedAs('status_code >= 400');

            // Time-series optimized indexes
            $table->index(['timestamp', 'endpoint']);
            $table->index(['endpoint', 'timestamp']);
            $table->index(['status_code', 'timestamp']);
            $table->index(['is_error', 'timestamp']);
            $table->index(['timestamp']); // Primary time-series index
        });

        // Pre-aggregated hourly statistics (for fast dashboard queries)
        Schema::connection('api_metrics')->create('api_hourly_stats', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 0-23
            $table->unsignedInteger('total_requests');
            $table->unsignedInteger('error_requests');
            $table->decimal('avg_response_time_ms', 10, 2);
            $table->decimal('min_response_time_ms', 10, 2);
            $table->decimal('max_response_time_ms', 10, 2);
            $table->decimal('p95_response_time_ms', 10, 2);
            $table->decimal('p99_response_time_ms', 10, 2);
            $table->decimal('avg_memory_usage_mb', 8, 4);
            $table->decimal('max_memory_usage_mb', 8, 4);
            $table->decimal('avg_cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('max_cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('total_request_size_mb', 10, 2);
            $table->decimal('total_response_size_mb', 10, 2);
            $table->decimal('requests_per_minute', 8, 2);
            $table->decimal('error_rate_percent', 5, 2);
            $table->timestamps();

            // Unique constraint and optimized indexes
            $table->unique(['endpoint', 'method', 'date', 'hour']);
            $table->index(['date', 'hour']);
            $table->index(['endpoint', 'date']);
            $table->index(['error_rate_percent', 'date']);
        });

        // Daily rollup statistics (for long-term trends)
        Schema::connection('api_metrics')->create('api_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->date('date');
            $table->unsignedInteger('total_requests');
            $table->unsignedInteger('error_requests');
            $table->decimal('avg_response_time_ms', 10, 2);
            $table->decimal('p95_response_time_ms', 10, 2);
            $table->decimal('p99_response_time_ms', 10, 2);
            $table->decimal('avg_memory_usage_mb', 8, 4);
            $table->decimal('avg_cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('total_request_size_mb', 10, 2);
            $table->decimal('total_response_size_mb', 10, 2);
            $table->decimal('requests_per_minute', 8, 2);
            $table->decimal('error_rate_percent', 5, 2);
            $table->decimal('uptime_percent', 5, 2);
            $table->timestamps();

            $table->unique(['endpoint', 'method', 'date']);
            $table->index('date');
            $table->index(['endpoint', 'date']);
        });

        // Top slow endpoints (for alerts and optimization)
        Schema::connection('api_metrics')->create('api_slow_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 255);
            $table->string('method', 10);
            $table->decimal('response_time_ms', 10, 2);
            $table->decimal('memory_usage_mb', 8, 4);
            $table->timestamp('timestamp');
            $table->text('context')->nullable(); // Additional context for debugging

            $table->index(['response_time_ms', 'timestamp']);
            $table->index(['endpoint', 'timestamp']);
        });

        // System-wide metrics summary (for dashboard overview)
        Schema::connection('api_metrics')->create('api_system_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp');
            $table->unsignedInteger('total_requests_per_minute');
            $table->decimal('avg_response_time_ms', 10, 2);
            $table->decimal('error_rate_percent', 5, 2);
            $table->unsignedSmallInteger('active_endpoints');
            $table->decimal('avg_memory_usage_mb', 8, 4);
            $table->decimal('avg_cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('throughput_mb_per_minute', 10, 2);
            $table->unsignedInteger('unique_users');

            $table->index('timestamp');
        });

        // SQLite specific optimizations
        DB::connection('api_metrics')->statement('PRAGMA journal_mode=WAL');
        DB::connection('api_metrics')->statement('PRAGMA synchronous=NORMAL');
        DB::connection('api_metrics')->statement('PRAGMA cache_size=10000');
        DB::connection('api_metrics')->statement('PRAGMA temp_store=MEMORY');
        DB::connection('api_metrics')->statement('PRAGMA mmap_size=268435456'); // 256MB mmap

        // Create views for common queries
        DB::connection('api_metrics')->statement("
            CREATE VIEW api_real_time_stats AS 
            SELECT 
                endpoint,
                method,
                COUNT(*) as requests_last_5min,
                AVG(response_time_ms) as avg_response_time,
                MAX(response_time_ms) as max_response_time,
                AVG(memory_usage_mb) as avg_memory,
                SUM(CASE WHEN is_error THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as error_rate
            FROM api_requests 
            WHERE timestamp >= datetime('now', '-5 minutes')
            GROUP BY endpoint, method
        ");
    }

    public function down()
    {
        Schema::connection('api_metrics')->dropIfExists('api_system_metrics');
        Schema::connection('api_metrics')->dropIfExists('api_slow_endpoints');
        Schema::connection('api_metrics')->dropIfExists('api_daily_stats');
        Schema::connection('api_metrics')->dropIfExists('api_hourly_stats');
        Schema::connection('api_metrics')->dropIfExists('api_requests');
    }
};
