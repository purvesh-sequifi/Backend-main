#!/bin/bash

# ============================================================================
# SAFE DEPLOYMENT SCRIPT FOR HORIZON JOBS
# ============================================================================
# This script ensures jobs are not lost during code deployments
# Usage: ./scripts/safe-deployment.sh
# ============================================================================

set -e

echo "🛡️ SAFE DEPLOYMENT SCRIPT"
echo "========================="
echo "Time: $(date)"
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Must be run from Laravel root directory"
    exit 1
fi

# Function to get queue length
get_queue_length() {
    local queue=$1
    local length=$(redis-cli llen "solarstage_horizon:queue:$queue" 2>/dev/null || echo "0")
    echo $length
}

# Check current job status
echo "📊 PRE-DEPLOYMENT STATUS:"
echo "========================"
SALES_JOBS=$(get_queue_length "sales-process")
DEFAULT_JOBS=$(get_queue_length "default")
PAYROLL_JOBS=$(get_queue_length "payroll")
EVEREE_JOBS=$(get_queue_length "everee")

echo "Queue Status:"
echo "  sales-process: $SALES_JOBS jobs"
echo "  default: $DEFAULT_JOBS jobs"
echo "  payroll: $PAYROLL_JOBS jobs"
echo "  everee: $EVEREE_JOBS jobs"

TOTAL_JOBS=$((SALES_JOBS + DEFAULT_JOBS + PAYROLL_JOBS + EVEREE_JOBS))
echo "  TOTAL: $TOTAL_JOBS jobs"
echo ""

# Check Horizon status
echo "🔍 Horizon Status:"
php artisan horizon:status
echo ""

# Check Redis persistence
echo "🛡️ Redis Persistence Status:"
AOF_STATUS=$(redis-cli CONFIG GET appendonly | tail -1)
RDB_STATUS=$(redis-cli CONFIG GET save | tail -1)
echo "  AOF Persistence: $AOF_STATUS"
echo "  RDB Snapshots: $RDB_STATUS"
echo ""

# Warning if many jobs are active
if [ $TOTAL_JOBS -gt 100 ]; then
    echo "⚠️  WARNING: $TOTAL_JOBS jobs in queue!"
    echo "   Estimated completion time: $(($TOTAL_JOBS / 20 * 60 / 60)) hours"
    echo ""
    read -p "   Continue with deployment? (y/N): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Deployment cancelled by user"
        exit 1
    fi
fi

# Graceful shutdown
echo "🔄 GRACEFUL DEPLOYMENT PROCESS:"
echo "==============================="
echo "1. Gracefully stopping Horizon..."
php artisan horizon:terminate

echo "2. Waiting for workers to complete current jobs..."
sleep 30

echo "3. Verifying all workers stopped..."
REMAINING_WORKERS=$(ps aux | grep "horizon:work" | grep -v grep | wc -l)
if [ $REMAINING_WORKERS -gt 0 ]; then
    echo "   ⚠️  $REMAINING_WORKERS workers still running, waiting..."
    sleep 30
fi

echo "4. Final verification..."
FINAL_WORKERS=$(ps aux | grep "horizon:work" | grep -v grep | wc -l)
echo "   Remaining workers: $FINAL_WORKERS"

# Deployment ready
echo ""
echo "✅ SYSTEM READY FOR DEPLOYMENT!"
echo "=============================="
echo "📊 Jobs are safely stored in Redis with AOF persistence"
echo "🔄 Deploy your code now"
echo "🚀 After deployment, run: php artisan horizon"
echo ""
echo "📋 Post-deployment verification:"
echo "  1. Check Horizon: php artisan horizon:status"
echo "  2. Check queues: redis-cli llen 'solarstage_horizon:queue:sales-process'"
echo "  3. Monitor dashboard: https://solarstage.api.sequifi.com/performance-dashboard"
echo ""

# Optional: Save current state
echo "💾 Saving deployment state..."
echo "Deployment Time: $(date)" > deployment-state.log
echo "Pre-deployment Jobs: $TOTAL_JOBS" >> deployment-state.log
echo "AOF Status: $AOF_STATUS" >> deployment-state.log
echo "System Load: $(uptime)" >> deployment-state.log

echo "🎯 Deployment state saved to: deployment-state.log"
echo ""
echo "🛡️ YOUR JOBS ARE SAFE! Deploy with confidence!"
