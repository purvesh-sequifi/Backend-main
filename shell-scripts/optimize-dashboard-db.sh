#!/bin/bash

# Dashboard Performance Optimization Script
# Run this after deploying the new indexes

echo "Starting Dashboard Database Optimization..."

# 1. Run the critical indexes migration
php artisan migrate --path=database/migrations/2024_01_01_000001_add_dashboard_performance_indexes.php

# 2. Analyze tables for query optimization
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" << EOF

-- Analyze tables for better query planning
ANALYZE TABLE user_override_history;
ANALYZE TABLE user_redlines;
ANALYZE TABLE user_commission_history;
ANALYZE TABLE user_upfront_history;
ANALYZE TABLE user_withheld_history;
ANALYZE TABLE user_organization_history;
ANALYZE TABLE onboarding_employees;
ANALYZE TABLE approvals_and_requests;
ANALYZE TABLE documents;
ANALYZE TABLE sale_master_process;
ANALYZE TABLE sale_masters;
ANALYZE TABLE users;

-- Check index usage
SHOW INDEX FROM user_override_history;
SHOW INDEX FROM sale_master_process;
SHOW INDEX FROM sale_masters;

-- Optimize tables
OPTIMIZE TABLE user_override_history;
OPTIMIZE TABLE user_redlines;
OPTIMIZE TABLE user_commission_history;
OPTIMIZE TABLE sale_master_process;
OPTIMIZE TABLE sale_masters;

EOF

echo "Database optimization completed!"

# 3. Clear query cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

echo "Cache cleared. Dashboard should now load significantly faster!"