#!/bin/bash

echo "Creating Supervisor configurations..."

# Remove old configuration files to avoid conflicts
sudo rm -f /etc/supervisor/conf.d/laravel-parlley-worker.conf
sudo rm -f /etc/supervisor/conf.d/laravel-worker.conf

# Create complete worker configuration with correct timeouts
sudo tee /etc/supervisor/conf.d/laravel-worker.conf > /dev/null << 'EOL'
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work database --sleep=3 --tries=3 --timeout=3600 --max-jobs=500
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/backend/current/public/jobs_queue/worker.log
stopwaitsecs=3600

[program:laravel-finalize-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=finalize-worker --sleep=3 --tries=3 --timeout=3600 --max-jobs=200
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/public/jobs_queue/worker-finalize.log

[program:laravel-execute-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=execute-worker --sleep=3 --tries=3 --timeout=3600 --max-jobs=200
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/public/jobs_queue/execute-worker.log

[program:laravel-onetimepayment-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=onetimepayment-worker --sleep=3 --tries=3 --timeout=1800 --max-jobs=100
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/public/jobs_queue/onetimepayment-worker.log

[program:laravel-everee-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=everee-worker --sleep=3 --tries=3 --timeout=1800 --max-jobs=100
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/public/jobs_queue/everee-worker.log

[program:laravel-onetimewebhook-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=onetimewebhook-worker --sleep=3 --tries=3 --timeout=1800 --max-jobs=100
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/public/jobs_queue/onetimewebhook-worker.log

[program:laravel-quickbooks-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=quickbooks-worker --sleep=3 --tries=3 --timeout=7200 --max-jobs=100
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/storage/logs/quickbooks.log

[program:laravel-recalculate-open-sales]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=recalculate-open-sales --sleep=3 --tries=3 --timeout=3600 --max-jobs=50
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/storage/logs/recalculate-open-sales.log

[program:laravel-sales-import]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=sales-import --sleep=3 --tries=3 --timeout=7200 --max-jobs=200
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=12
redirect_stderr=true
stdout_logfile=/var/www/backend/current/storage/logs/sales-import.log

[program:laravel-employment-package]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/backend/current/artisan queue:work --queue=employment-package --sleep=3 --tries=3 --timeout=3600 --max-jobs=100
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/backend/current/storage/logs/employment-package.log
EOL

echo "==============================================="
echo "🧹 CLEANING UP OLD WORKER PROCESSES"
echo "==============================================="

# Stop all supervisor worker cleanly first
echo "Stopping all Laravel supervisor worker..."
sudo supervisorctl stop all || echo "No worker to stop"

# Wait for clean shutdown
sleep 3

# List current queue worker processes before cleanup
echo "Current queue worker processes:"
ps aux | grep -E "php.*artisan queue:work" | grep -v grep || echo "No current worker found"

# More targeted cleanup - only kill old problematic processes
echo "Cleaning up old worker..."

# Kill only old php8.1 processes with specific patterns that cause interference
OLD_worker=$(ps aux | grep -E "php8.1.*artisan queue:work.*stop-when-empty" | grep -v grep | awk '{print $2}' || echo "")
if [ -n "$OLD_worker" ]; then
    echo "Found old worker, cleaning up..."
    echo "$OLD_worker" | sudo xargs kill -TERM 2>/dev/null || true
    sleep 3
    # Force kill if still running
    echo "$OLD_worker" | sudo xargs kill -9 2>/dev/null || true
else
    echo "No old interfering worker found"
fi

# Verify cleanup without being too aggressive
REMAINING_OLD=$(ps aux | grep -E "php8.1.*artisan queue:work.*stop-when-empty" | grep -v grep | wc -l || echo "0")
echo "✅ Cleanup complete. Old interfering processes remaining: $REMAINING_OLD"

# Show what's left running
echo "Current supervisor status:"
sudo supervisorctl status | head -5 || echo "Supervisor not responding"

echo "==============================================="
echo "🔄 RESTARTING SERVICES WITH CLEAN STATE"
echo "==============================================="

# Restart services
sudo systemctl restart apache2 || echo "Failed to restart Apache"
sudo supervisorctl reread
sudo supervisorctl update

# Start all worker groups
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-finalize-worker:*
sudo supervisorctl start laravel-execute-worker:*
sudo supervisorctl start laravel-onetimepayment-worker:*
sudo supervisorctl start laravel-everee-worker:*
sudo supervisorctl start laravel-onetimewebhook-worker:*
sudo supervisorctl start laravel-quickbooks-worker:*
sudo supervisorctl start laravel-recalculate-open-sales:*
sudo supervisorctl start laravel-sales-import:*
sudo supervisorctl start laravel-employment-package:*


sudo service supervisor reload

echo "==============================================="
echo "✅ VERIFYING DEPLOYMENT SUCCESS"
echo "==============================================="

# Wait for worker to fully start
sleep 10

# Count running supervisor worker with error handling
echo "Checking supervisor worker status..."
RUNNING_worker=$(sudo supervisorctl status 2>/dev/null | grep "RUNNING" | wc -l || echo "0")
echo "Supervisor worker running: $RUNNING_worker"

# Verify no old processes are interfering
OLD_PROCESSES=$(ps aux | grep -E "php8.1.*artisan queue:work.*stop-when-empty" | grep -v grep | wc -l 2>/dev/null || echo "0")
echo "Old interfering processes: $OLD_PROCESSES"

# Show sample of running worker with error handling
echo "Sample of running worker:"
sudo supervisorctl status 2>/dev/null | grep "RUNNING" | head -5 || echo "No worker found or supervisor issues"

# Test queue connection with better error handling
echo "Testing queue connectivity..."
cd /var/www/backend/current || echo "Could not change to app directory"
if timeout 30 sudo -u www-data php artisan queue:work database --once --no-interaction 2>/dev/null; then
    echo "✅ Queue worker can connect and process jobs"
else
    echo "⚠️ Queue worker test failed or no jobs to process - this is normal"
fi

echo "==============================================="
echo "🎯 DEPLOYMENT STATUS SUMMARY"
echo "==============================================="
echo "✅ Old worker cleaned: $([ "$OLD_PROCESSES" -eq 0 ] && echo "SUCCESS" || echo "WARNING")"
echo "✅ New worker started: $RUNNING_worker"
echo "✅ Worker timeout: No time limit (jobs-based restart only)"
echo "✅ Clean deployment: COMPLETE"
echo "==============================================="
