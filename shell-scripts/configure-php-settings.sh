#!/bin/bash
# Configure PHP settings for all PHP 8.3 configurations
# This script increases max_execution_time from 30 to 300 seconds (5 minutes)
# Applies to: Apache2 module, PHP-FPM, CLI, CGI, and any custom configurations

set -e

echo "=============================================="
echo "🔧 Configuring PHP Settings for All Modules"
echo "=============================================="
echo ""

# Find all PHP 8.3 ini files (standard locations)
PHP_INI_FILES=(
    "/etc/php/8.3/apache2/php.ini"
    "/etc/php/8.3/cli/php.ini"
    "/etc/php/8.3/fpm/php.ini"
    "/etc/php/8.3/cgi/php.ini"
    "/etc/php/8.3/mods-available/custom.ini"
)

# Also search for any additional PHP 8.3 configuration files
if [ -d "/etc/php/8.3" ]; then
    echo "🔍 Searching for additional PHP 8.3 configuration files..."
    while IFS= read -r -d '' file; do
        if [[ ! " ${PHP_INI_FILES[@]} " =~ " ${file} " ]]; then
            PHP_INI_FILES+=("$file")
        fi
    done < <(find /etc/php/8.3 -name "php.ini" -print0 2>/dev/null || true)
fi

echo "Found ${#PHP_INI_FILES[@]} PHP configuration file(s) to update"
echo ""

# Function to update a specific setting in php.ini
update_php_setting() {
    local PHP_INI=$1
    local SETTING_NAME=$2
    local SETTING_VALUE=$3
    
    # Update the setting to 300 (handles all PHP ini format variations)
    # Check for active setting (with or without spaces around =)
    if grep -qE "^${SETTING_NAME}\s*=" "$PHP_INI"; then
        # Replace existing active setting (handles both "setting=value" and "setting = value")
        sudo sed -i "s/^${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$PHP_INI"
    # Check for commented setting (with or without space after semicolon)
    elif grep -qE "^;\s*${SETTING_NAME}\s*=" "$PHP_INI"; then
        # Uncomment and replace (handles both ";setting" and "; setting")
        sudo sed -i "s/^;\s*${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$PHP_INI"
    else
        # Setting doesn't exist, append it
        echo "${SETTING_NAME} = ${SETTING_VALUE}" | sudo tee -a "$PHP_INI" > /dev/null
    fi
}

# Function to update PHP-FPM pool settings
update_fpm_pool_setting() {
    local POOL_CONF=$1
    local SETTING_NAME=$2
    local SETTING_VALUE=$3
    
    # Update the setting to 300 (handles all PHP-FPM conf format variations)
    # Check for active setting (with or without spaces around =)
    if grep -qE "^${SETTING_NAME}\s*=" "$POOL_CONF"; then
        # Replace existing active setting (handles both "setting=value" and "setting = value")
        sudo sed -i "s/^${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$POOL_CONF"
    # Check for commented setting (with or without space after semicolon)
    elif grep -qE "^;\s*${SETTING_NAME}\s*=" "$POOL_CONF"; then
        # Uncomment and replace (handles both ";setting" and "; setting")
        sudo sed -i "s/^;\s*${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$POOL_CONF"
    else
        # Setting doesn't exist, append it
        echo "${SETTING_NAME} = ${SETTING_VALUE}" | sudo tee -a "$POOL_CONF" > /dev/null
    fi
}

echo "=============================================="
echo "📝 STEP 1: Updating PHP INI Files"
echo "=============================================="
echo ""

# Backup and update each php.ini file
for PHP_INI in "${PHP_INI_FILES[@]}"; do
    if [ -f "$PHP_INI" ]; then
        echo "📝 Updating: $PHP_INI"
        
        # Create backup
        BACKUP_FILE="${PHP_INI}.backup-$(date +%Y%m%d)"
        if [ ! -f "$BACKUP_FILE" ]; then
            sudo cp "$PHP_INI" "$BACKUP_FILE"
        fi
        
        # Set timeout values to 300 seconds
        update_php_setting "$PHP_INI" "max_execution_time" "300"
        update_php_setting "$PHP_INI" "max_input_time" "300"
        update_php_setting "$PHP_INI" "default_socket_timeout" "300"
        
        echo "✅ Updated $PHP_INI"
        echo ""
    fi
done

echo "=============================================="
echo "📝 STEP 2: Updating PHP-FPM Pool Configurations"
echo "=============================================="
echo ""

# Find and update PHP-FPM pool configurations
FPM_POOL_DIR="/etc/php/8.3/fpm/pool.d"
if [ -d "$FPM_POOL_DIR" ]; then
    echo "🔍 Searching for PHP-FPM pool configurations in: $FPM_POOL_DIR"
    
    FPM_POOL_CONFIGS=()
    while IFS= read -r -d '' file; do
        FPM_POOL_CONFIGS+=("$file")
    done < <(find "$FPM_POOL_DIR" -name "*.conf" -print0 2>/dev/null || true)
    
    if [ ${#FPM_POOL_CONFIGS[@]} -gt 0 ]; then
        echo "Found ${#FPM_POOL_CONFIGS[@]} FPM pool configuration(s)"
        echo ""
        
        for POOL_CONF in "${FPM_POOL_CONFIGS[@]}"; do
            echo "📝 Updating FPM Pool: $POOL_CONF"
            
            # Create backup
            BACKUP_FILE="${POOL_CONF}.backup-$(date +%Y%m%d)"
            if [ ! -f "$BACKUP_FILE" ]; then
                sudo cp "$POOL_CONF" "$BACKUP_FILE"
            fi
            
            # Set request_terminate_timeout to 300 seconds
            update_fpm_pool_setting "$POOL_CONF" "request_terminate_timeout" "300"
            
            echo "✅ Updated $POOL_CONF"
            echo ""
        done
    else
        echo "⏭️  No FPM pool configurations found"
        echo ""
    fi
else
    echo "⏭️  PHP-FPM pool directory not found: $FPM_POOL_DIR"
    echo ""
fi

echo "=============================================="
echo "🔍 STEP 3: Verifying Apache2 PHP Module"
echo "=============================================="
echo ""

if command -v apache2ctl >/dev/null 2>&1; then
    if apache2ctl -M 2>/dev/null | grep -q "php"; then
        echo "✅ Apache2 PHP module is loaded"
        apache2ctl -M 2>/dev/null | grep "php" || true
    else
        echo "⚠️  Warning: Apache2 PHP module not detected"
        echo "   If using PHP-FPM with Apache2, this is normal"
    fi
else
    echo "⏭️  Apache2 not found or not accessible"
fi

echo ""

echo "=============================================="
echo "🔍 STEP 4: Verifying PHP-FPM Service"
echo "=============================================="
echo ""

if command -v php-fpm8.3 >/dev/null 2>&1; then
    echo "✅ PHP-FPM 8.3 binary found"
    
    # Check if PHP-FPM service is running
    if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
        echo "✅ PHP-FPM 8.3 service is running"
    else
        echo "⚠️  PHP-FPM 8.3 service is not running (will be started on next deployment)"
    fi
else
    echo "⏭️  PHP-FPM 8.3 not installed or not in PATH"
fi

echo ""
echo "=============================================="
echo "✅ PHP Configuration Complete"
echo "=============================================="
echo ""
echo "Summary of changes applied to ALL PHP 8.3 configurations:"
echo ""
echo "📌 PHP INI Settings (Apache2/CLI/FPM/CGI):"
echo "  - max_execution_time: 30s → 300s (5 minutes)"
echo "  - max_input_time: 60s → 300s (5 minutes)"
echo "  - default_socket_timeout: 60s → 300s (5 minutes)"
echo ""
echo "📌 PHP-FPM Pool Settings:"
echo "  - request_terminate_timeout: 30s → 300s (5 minutes)"
echo ""
echo "Configured files:"
for PHP_INI in "${PHP_INI_FILES[@]}"; do
    if [ -f "$PHP_INI" ]; then
        echo "  ✅ $PHP_INI"
    fi
done

if [ ${#FPM_POOL_CONFIGS[@]} -gt 0 ]; then
    for POOL_CONF in "${FPM_POOL_CONFIGS[@]}"; do
        if [ -f "$POOL_CONF" ]; then
            echo "  ✅ $POOL_CONF"
        fi
    done
fi

echo ""
echo "⚠️  IMPORTANT: Services must be restarted for changes to take effect:"
echo "   • Apache2: sudo systemctl restart apache2"
echo "   • PHP-FPM: sudo systemctl restart php8.3-fpm"
echo "=============================================="

