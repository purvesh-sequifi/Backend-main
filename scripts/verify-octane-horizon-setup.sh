#!/bin/bash

# ============================================================================
# Octane + Horizon + Redis Configuration Verification Script
# ============================================================================
# This script verifies that all critical configurations are in place
# for Laravel Octane, Swoole, Redis, and Horizon.
#
# Usage:
#   bash scripts/verify-octane-horizon-setup.sh
#
# Exit Codes:
#   0 = All checks passed
#   1 = One or more checks failed
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Functions
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASSED++))
}

print_error() {
    echo -e "${RED}✗${NC} $1"
    ((FAILED++))
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARNINGS++))
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# ============================================================================
# START VERIFICATION
# ============================================================================

echo -e "${BLUE}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║   Octane + Horizon + Redis Configuration Verification     ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# ============================================================================
# 1. PHP & Extensions Check
# ============================================================================
print_header "1. PHP & Extensions"

# Check PHP version
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
    PHP_MAJOR=$(echo $PHP_VERSION | cut -d "." -f 1)
    PHP_MINOR=$(echo $PHP_VERSION | cut -d "." -f 2)
    if [ "$PHP_MAJOR" -ge 8 ] && [ "$PHP_MINOR" -ge 1 ]; then
        print_success "PHP $PHP_VERSION installed"
    else
        print_error "PHP $PHP_VERSION is too old (require 8.1+)"
    fi
else
    print_error "PHP not found"
fi

# Check Swoole extension
if php -m | grep -q swoole; then
    SWOOLE_VERSION=$(php -r "echo phpversion('swoole');")
    print_success "Swoole extension installed (v$SWOOLE_VERSION)"
else
    print_error "Swoole extension not installed"
fi

# Check Redis extension (optional but recommended)
if php -m | grep -q redis; then
    print_success "PHP Redis extension installed (optional)"
else
    print_warning "PHP Redis extension not installed (Predis will be used)"
fi

# ============================================================================
# 2. Redis Server Check
# ============================================================================
print_header "2. Redis Server"

# Check if Redis is installed
if command -v redis-cli &> /dev/null; then
    print_success "Redis CLI installed"
    
    # Check if Redis is running
    if redis-cli ping &> /dev/null; then
        print_success "Redis server is running"
        
        # Check Redis version
        REDIS_VERSION=$(redis-cli INFO | grep redis_version | cut -d ':' -f 2 | tr -d '\r')
        print_info "Redis version: $REDIS_VERSION"
        
        # Check Redis databases
        REDIS_DATABASES=$(redis-cli CONFIG GET databases | tail -n 1)
        if [ "$REDIS_DATABASES" -ge 3 ]; then
            print_success "Redis has $REDIS_DATABASES databases (need 3+)"
        else
            print_warning "Redis has only $REDIS_DATABASES databases (recommend 16+)"
        fi
        
        # Check Redis persistence
        AOF_ENABLED=$(redis-cli CONFIG GET appendonly | tail -n 1)
        if [ "$AOF_ENABLED" = "yes" ]; then
            print_success "Redis AOF persistence enabled"
        else
            print_warning "Redis AOF persistence disabled (recommended for queues)"
        fi
    else
        print_error "Redis server is not running"
    fi
else
    print_error "Redis not installed"
fi

# ============================================================================
# 3. Laravel Configuration Files
# ============================================================================
print_header "3. Laravel Configuration Files"

# Check if we're in Laravel directory
if [ ! -f "artisan" ]; then
    print_error "Not in Laravel root directory (artisan not found)"
    exit 1
fi

# Check config files exist
CONFIG_FILES=(
    "config/octane.php"
    "config/horizon.php"
    "config/queue.php"
    "config/cache.php"
    "config/database.php"
)

for config_file in "${CONFIG_FILES[@]}"; do
    if [ -f "$config_file" ]; then
        print_success "$config_file exists"
    else
        print_error "$config_file not found"
    fi
done

# Check for Swoole configuration in octane.php
if grep -q "'swoole' => \[" config/octane.php; then
    print_success "Swoole configuration section found in octane.php"
else
    print_error "Swoole configuration section missing in octane.php"
fi

# Check queue connection in queue.php
if grep -q "QUEUE_CONNECTION.*redis" config/queue.php; then
    print_success "Queue connection set to redis in queue.php"
else
    print_warning "Queue connection may not be set to redis"
fi

# Check Redis queue connection in database.php
if grep -q "'queue' =>" config/database.php; then
    print_success "Redis queue database connection configured"
else
    print_error "Redis queue database connection missing"
fi

# ============================================================================
# 4. Service Provider Check
# ============================================================================
print_header "4. Service Providers"

# Check HorizonServiceProvider
if [ -f "app/Providers/HorizonServiceProvider.php" ]; then
    print_success "HorizonServiceProvider exists"
    
    # Check if registered in config/app.php
    if grep -q "HorizonServiceProvider" config/app.php; then
        print_success "HorizonServiceProvider registered in config/app.php"
    else
        print_warning "HorizonServiceProvider not registered in config/app.php"
    fi
else
    print_error "HorizonServiceProvider not found"
fi

# ============================================================================
# 5. Composer Packages
# ============================================================================
print_header "5. Required Composer Packages"

REQUIRED_PACKAGES=(
    "laravel/octane"
    "laravel/horizon"
    "predis/predis"
)

for package in "${REQUIRED_PACKAGES[@]}"; do
    if grep -q "\"$package\"" composer.json; then
        print_success "$package installed"
    else
        print_error "$package not found in composer.json"
    fi
done

# ============================================================================
# 6. Environment Variables
# ============================================================================
print_header "6. Environment Variables"

if [ -f ".env" ]; then
    print_success ".env file exists"
    
    # Check critical environment variables
    ENV_VARS=(
        "QUEUE_CONNECTION"
        "CACHE_DRIVER"
        "SESSION_DRIVER"
        "REDIS_HOST"
        "REDIS_PORT"
        "OCTANE_SERVER"
    )
    
    for var in "${ENV_VARS[@]}"; do
        if grep -q "^$var=" .env; then
            VALUE=$(grep "^$var=" .env | cut -d '=' -f 2)
            print_success "$var=$VALUE"
        else
            print_warning "$var not set in .env"
        fi
    done
else
    print_error ".env file not found"
fi

# ============================================================================
# 7. Supervisor Configuration
# ============================================================================
print_header "7. Supervisor Configuration"

if command -v supervisorctl &> /dev/null; then
    print_success "Supervisor installed"
    
    # Check for Octane supervisor config
    if [ -f "/etc/supervisor/conf.d/sequifi-octane.conf" ]; then
        print_success "Octane supervisor config exists"
    else
        print_warning "Octane supervisor config not found"
    fi
    
    # Check for Horizon supervisor config
    if [ -f "/etc/supervisor/conf.d/sequifi-horizon.conf" ]; then
        print_success "Horizon supervisor config exists"
    else
        print_warning "Horizon supervisor config not found"
    fi
else
    print_warning "Supervisor not installed (optional for production)"
fi

# ============================================================================
# 8. Storage Permissions
# ============================================================================
print_header "8. Storage & Permissions"

WRITABLE_DIRS=(
    "storage/logs"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/app"
)

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ -d "$dir" ] && [ -w "$dir" ]; then
        print_success "$dir is writable"
    else
        print_error "$dir is not writable"
    fi
done

# ============================================================================
# 9. Artisan Commands Test
# ============================================================================
print_header "9. Laravel Artisan Commands"

# Test config cache
if php artisan config:cache &> /dev/null; then
    print_success "Config cache works"
else
    print_error "Config cache failed"
fi

# Test Octane command
if php artisan octane:status &> /dev/null; then
    print_success "Octane commands available"
else
    print_warning "Octane commands not available"
fi

# Test Horizon command
if php artisan horizon:status &> /dev/null; then
    print_success "Horizon commands available"
else
    print_warning "Horizon commands not available"
fi

# ============================================================================
# 10. Optional Optimizations
# ============================================================================
print_header "10. Optional Optimizations"

# Check for OctaneResponseCache middleware
if [ -f "app/Http/Middleware/OctaneResponseCache.php" ]; then
    print_success "OctaneResponseCache middleware exists"
else
    print_warning "OctaneResponseCache middleware not found (optional)"
fi

# Check for Redis persistence config
if [ -f "config/redis-persistence.conf" ]; then
    print_success "Redis persistence config template exists"
else
    print_warning "Redis persistence config template not found"
fi

# Check if Horizon snapshots are scheduled
if grep -q "horizon:snapshot" app/Console/Kernel.php; then
    print_success "Horizon snapshot scheduled in Kernel"
else
    print_warning "Horizon snapshot not scheduled (recommended)"
fi

# ============================================================================
# SUMMARY
# ============================================================================
print_header "VERIFICATION SUMMARY"

echo ""
echo -e "${GREEN}Passed:   $PASSED${NC}"
echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
echo -e "${RED}Failed:   $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ ALL CRITICAL CHECKS PASSED!                ║${NC}"
    echo -e "${GREEN}║    Your setup is ready for production!        ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════╝${NC}"
    
    if [ $WARNINGS -gt 0 ]; then
        echo ""
        echo -e "${YELLOW}Note: You have $WARNINGS warning(s). Review them for optimization opportunities.${NC}"
    fi
    
    exit 0
else
    echo -e "${RED}╔════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ VERIFICATION FAILED                         ║${NC}"
    echo -e "${RED}║    Please fix the errors above                 ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}Next steps:${NC}"
    echo "1. Review the errors marked with ✗ above"
    echo "2. Check documentation: OCTANE_HORIZON_ENV_COMPLETE.md"
    echo "3. Run this script again after fixes"
    exit 1
fi

