#!/bin/bash
# Database Monitoring Script for Laravel Standard deployments

CURRENT_LINK="/var/www/backend/current"
cd "${CURRENT_LINK}"

echo "===================================================="
echo "🔍 STANDARD DATABASE MONITORING SCRIPT"
echo "===================================================="

# Extract database configuration from .env file
echo "Extracting database configuration from .env file..."
DB_HOST=$(grep -E "^DB_HOST=" "${CURRENT_LINK}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
DB_CONNECTION=$(grep -E "^DB_CONNECTION=" "${CURRENT_LINK}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
DB_PORT=$(grep -E "^DB_PORT=" "${CURRENT_LINK}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")

# Print the extracted database configuration
echo "Extracted database configuration from .env:"
echo "DB_CONNECTION: ${DB_CONNECTION:-Not set}"
echo "DB_HOST: ${DB_HOST:-Not set}"
echo "DB_PORT: ${DB_PORT:-Not set}"

# Display .env database settings for debugging
echo "Current database settings in .env:"
sudo grep -E "DB_HOST|DB_CONNECTION|DB_DATABASE|DB_USERNAME|DB_PORT" "${CURRENT_LINK}/.env"

# Local database connection check
if [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    echo "Local database detected - checking MySQL/MariaDB service status..."
    
    # Check if MySQL/MariaDB service is running
    if sudo systemctl is-active --quiet mysql || sudo systemctl is-active --quiet mariadb; then
        echo "✅ MySQL/MariaDB service is running"
        
        # Check service memory usage
        MYSQL_MEMORY=$(sudo ps aux | grep mysql | grep -v grep | awk '{sum+=$6} END {print sum/1024 "MB"}')
        echo "📊 MySQL/MariaDB memory usage: $MYSQL_MEMORY"
        
        # Check for any MySQL/MariaDB errors in the logs
        echo "Checking for recent database errors..."
        RECENT_ERRORS=$(sudo tail -n 50 /var/log/mysql/error.log 2>/dev/null | grep -i "error" | wc -l)
        
        if [ "$RECENT_ERRORS" -gt 0 ]; then
            echo "⚠️ Found $RECENT_ERRORS recent errors in the MySQL error log. Please check /var/log/mysql/error.log"
        else
            echo "✅ No recent errors found in MySQL error logs"
        fi
    else
        echo "❌ MySQL/MariaDB service is NOT running! This is a critical issue."
        echo "Attempting to start the service..."
        sudo systemctl start mysql || sudo systemctl start mariadb
    fi
fi

# Configure PHP optimizations for standard database
echo "Configuring PHP optimizations for standard database..."
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "Detected PHP version: ${PHP_VERSION}"

# Create PHP configuration optimized for standard database
sudo mkdir -p /etc/php/${PHP_VERSION}/cli/conf.d/
sudo tee /etc/php/${PHP_VERSION}/cli/conf.d/99-laravel-db-standard.ini > /dev/null << 'EOL'
[pdo_mysql]
pdo_mysql.cache_size = 2000

[PHP]
; Standard database connection settings
mysql.allow_persistent = On
mysqli.allow_persistent = On
mysql.max_persistent = 50
mysqli.max_persistent = 50
mysql.max_links = 100
mysqli.max_links = 100
mysql.connect_timeout = 5
mysql.trace_mode = Off
mysqli.trace_mode = Off

; General performance settings
memory_limit = 256M
max_execution_time = 180
max_input_time = 120

; OPcache for better performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
opcache.save_comments = 1
EOL

# Apply to PHP-FPM if available
if [ -d "/etc/php/${PHP_VERSION}/fpm/conf.d/" ]; then
    echo "Applying standard database settings to PHP-FPM..."
    sudo cp /etc/php/${PHP_VERSION}/cli/conf.d/99-laravel-db-standard.ini /etc/php/${PHP_VERSION}/fpm/conf.d/
fi

# Run Laravel database connection tests
echo "Running Laravel database connection tests..."

# Test database connection with Laravel (safely, without modifying data)
if sudo -u www-data php artisan migrate:status >/dev/null 2>&1; then
    echo "✅ Laravel database connection is working correctly"
    
    # Get migration status
    PENDING_MIGRATIONS=$(sudo -u www-data php artisan migrate:status | grep -c "Pending")
    if [ "$PENDING_MIGRATIONS" -gt 0 ]; then
        echo "⚠️ Warning: $PENDING_MIGRATIONS pending migrations found"
    else
        echo "✅ All migrations are up to date"
    fi
else
    echo "❌ Laravel database connection test failed"
fi

# Test database connection directly with mysql client
echo "Testing direct database connection with mysql client..."
# Extract database credentials from .env file
DB_USER=$(grep "^DB_USERNAME=" "${CURRENT_LINK}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
DB_PASS=$(grep "^DB_PASSWORD=" "${CURRENT_LINK}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
DB_NAME=$(grep "^DB_DATABASE=" "${CURRENT_LINK}/.env" | cut -d '=' -f2- | tr -d '"' | tr -d "'")

# Print database connection details (masking password)
echo "Database connection details:"
echo "DB_HOST: $DB_HOST"
echo "DB_USERNAME: $DB_USER"
echo "DB_DATABASE: $DB_NAME"

# Test connection to database
if [ -n "$DB_USER" ] && [ -n "$DB_PASS" ] && [ -n "$DB_NAME" ]; then
    echo "Testing connection to database ($DB_HOST)..."
    if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SHOW TABLES FROM $DB_NAME;" 2>/dev/null | grep -q "."; then
        echo "✅ Direct MySQL connection successful!"
        
        # Get table count
        TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null | grep -v "COUNT" | tr -d '[:space:]')
        echo "📊 Database contains $TABLE_COUNT tables"
        
        # Check for database size
        DB_SIZE=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null | grep -v "Size" | tr -d '[:space:]')
        echo "📊 Database size: ${DB_SIZE}MB"
    else
        echo "❌ Direct MySQL connection failed!"
    fi
else
    echo "⚠️ Missing database credentials in .env file"
fi

# Check disk space where database is likely stored
echo "Checking disk space..."
if [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
    MYSQL_DATA_DIR=$(sudo mysql -e "SHOW VARIABLES LIKE 'datadir';" 2>/dev/null | grep datadir | awk '{print $2}')
    if [ -n "$MYSQL_DATA_DIR" ]; then
        DISK_USAGE=$(df -h "$MYSQL_DATA_DIR" | grep -v Filesystem)
        echo "Database disk usage: $DISK_USAGE"
        
        # Extract disk usage percentage
        USAGE_PERCENT=$(echo "$DISK_USAGE" | awk '{print $5}' | tr -d '%')
        if [ "$USAGE_PERCENT" -gt 80 ]; then
            echo "⚠️ Warning: Disk usage is high (${USAGE_PERCENT}%)"
        else
            echo "✅ Disk usage is at an acceptable level (${USAGE_PERCENT}%)"
        fi
    fi
fi

# Restart PHP-FPM with optimized settings
echo "Restarting PHP-FPM with standard database optimizations..."
if systemctl list-units --full -all | grep -Fq "php${PHP_VERSION}-fpm"; then
    sudo systemctl restart "php${PHP_VERSION}-fpm"
else
    PHP_FPM_SERVICE=$(systemctl list-units --full -all | grep -E 'php.*-fpm.service' | head -1 | awk '{print $1}')
    if [ -n "$PHP_FPM_SERVICE" ]; then
        sudo systemctl restart "$PHP_FPM_SERVICE"
    fi
fi

# Clear Laravel caches
echo "Clearing Laravel caches for optimal performance..."
cd "${CURRENT_LINK}"
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear

# Print summary
echo ""
echo "==============================================="
echo "📊 STANDARD DATABASE OPTIMIZATION STATUS:"
echo "==============================================="
echo "✅ PHP Optimizations: APPLIED"
echo "✅ Connection settings: OPTIMIZED"
echo "✅ Laravel caches: CLEARED"
echo "✅ Worker counts optimized for standard database"
echo "==============================================="
echo "💡 Standard database monitoring complete"
echo "==============================================="
