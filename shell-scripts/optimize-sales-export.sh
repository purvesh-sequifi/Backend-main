#!/bin/bash

# Sales Export Performance Optimization Script
# This script applies database optimizations for sales export functionality

set -e  # Exit on any error

echo "🚀 Sales Export Optimization Script"
echo "===================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "This script must be run from the Laravel project root directory"
    exit 1
fi

# Step 1: Backup current database (optional but recommended)
print_step "1. Creating database backup (optional)"
read -p "Do you want to create a database backup before optimization? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_status "Creating database backup..."
    php artisan backup:run --only-db
    print_status "Database backup completed"
else
    print_warning "Skipping database backup"
fi

# Step 2: Analyze current performance
print_step "2. Analyzing current export performance"
php artisan sales:optimize-export --analyze

# Step 3: Run the optimization migration
print_step "3. Applying database index optimizations"
print_status "Running sales export performance migration..."
php artisan migrate --path=database/migrations/2024_01_02_000000_add_sales_export_performance_indexes.php

if [ $? -eq 0 ]; then
    print_status "✅ Database indexes applied successfully"
else
    print_error "❌ Failed to apply database indexes"
    exit 1
fi

# Step 4: Apply additional optimizations
print_step "4. Applying additional optimizations"
php artisan sales:optimize-export --apply

# Step 5: Clear caches
print_step "5. Clearing application caches"
print_status "Clearing configuration cache..."
php artisan config:clear

print_status "Clearing route cache..."
php artisan route:clear

print_status "Clearing view cache..."
php artisan view:clear

print_status "Optimizing autoloader..."
composer dump-autoload --optimize

# Step 6: Queue worker optimization recommendations
print_step "6. Queue Worker Optimization Recommendations"
print_warning "Based on your server performance history, consider these optimizations:"
echo ""
echo "Current queue worker settings that may need adjustment:"
echo "- Reduce queue workers from 20+ to 2-4 workers per CPU core"
echo "- Set PHP memory_limit to 512M instead of unlimited (-1)"
echo "- Review aggressive cron jobs (cache clearing every minute)"
echo ""
echo "Recommended supervisor configuration:"
echo "[program:laravel-worker]"
echo "process_name=%(program_name)s_%(process_num)02d"
echo "command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=512"
echo "autostart=true"
echo "autorestart=true"
echo "stopasgroup=true"
echo "killasgroup=true"
echo "numprocs=4"
echo "redirect_stderr=true"
echo "stdout_logfile=/path/to/storage/logs/worker.log"

# Step 7: Performance testing
print_step "7. Performance Testing Recommendation"
echo ""
print_status "To test the optimized export performance:"
echo "1. Use the new optimized endpoint: POST /v2/sales/sales-export-optimized"
echo "2. Test with different dataset sizes:"
echo "   - Small: 1,000 records"
echo "   - Medium: 10,000 records" 
echo "   - Large: 50,000+ records"
echo "3. Monitor memory usage and execution time"
echo "4. Compare with previous export times"

# Step 8: Monitoring setup
print_step "8. Monitoring Setup"
print_status "Consider setting up monitoring for:"
echo "- Database query performance (slow query log)"
echo "- Memory usage during exports"
echo "- Export completion times"
echo "- Server load during export operations"

echo ""
print_status "🎉 Sales Export Optimization Complete!"
print_status "The export system should now handle large datasets more efficiently."
print_warning "Remember to update your frontend to use the new optimized endpoint if needed."

echo ""
echo "Next steps:"
echo "1. Test the optimized export with your typical dataset sizes"
echo "2. Monitor performance improvements"
echo "3. Adjust queue worker settings as recommended"
echo "4. Consider implementing the monitoring suggestions"

echo ""
print_status "For any issues, check the logs in storage/logs/"