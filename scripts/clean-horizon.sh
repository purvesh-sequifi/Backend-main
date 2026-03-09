#!/bin/bash
# Simple script to clean Horizon completed and failed jobs
# Usage: bash scripts/clean-horizon.sh

echo "🧹 Cleaning Horizon Job History..."
echo ""

# Get Horizon prefix from config
HORIZON_PREFIX="new_sequifi_horizon"

# 1. Clear Redis keys (the KEY step!)
echo "1. Clearing Redis keys..."
redis-cli -n 0 DEL ${HORIZON_PREFIX}:completed_jobs
redis-cli -n 0 DEL ${HORIZON_PREFIX}:recent_jobs
redis-cli -n 0 DEL ${HORIZON_PREFIX}:failed_jobs
redis-cli -n 0 DEL ${HORIZON_PREFIX}:recent_failed_jobs

# Delete individual job UUID keys
echo "2. Clearing job UUID keys..."
redis-cli -n 0 --scan --pattern "${HORIZON_PREFIX}:*-*-*-*-*" | xargs -r redis-cli -n 0 DEL

# 2. Clear database tables
echo "3. Clearing database tables..."
cd /var/www/backend/current
sudo -u www-data php artisan tinker --execute="
DB::table('failed_job_details')->delete();
DB::table('failed_jobs')->delete();
DB::table('job_performance_logs')->delete();
echo 'Database tables cleared\n';
"

# 3. Restart services
echo "4. Restarting Horizon and Octane..."
sudo supervisorctl restart sequifi-horizon
sudo supervisorctl restart sequifi-octane

sleep 3

echo ""
echo "✅ Horizon cleanup completed!"
echo "🔄 Hard refresh your browser (Ctrl+Shift+R) to see changes."
echo ""
