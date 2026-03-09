#!/bin/bash

# Laravel Storage Fix Script
# Fixes common deployment issues with storage directories and permissions

set -e  # Exit on any error

echo "🔧 Laravel Storage Fix Starting..."

# Configuration
CURRENT_DIR=$(pwd)
STORAGE_DIR="$CURRENT_DIR/storage"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if running as root or with sudo
check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        print_warning "Running as non-root user. Some operations may require sudo."
        print_status "If you encounter permission errors, run with: sudo $0"
    fi
}

# Function to create storage directory structure
create_storage_structure() {
    print_status "Creating Laravel storage directory structure..."
    
    # Main storage directory
    if [ ! -d "$STORAGE_DIR" ]; then
        mkdir -p "$STORAGE_DIR"
        print_success "Created storage directory: $STORAGE_DIR"
    fi
    
    # Required subdirectories
    local dirs=(
        "app"
        "framework"
        "framework/cache"
        "framework/cache/data"
        "framework/sessions"
        "framework/testing"
        "framework/views"
        "logs"
    )
    
    for dir in "${dirs[@]}"; do
        local full_path="$STORAGE_DIR/$dir"
        if [ ! -d "$full_path" ]; then
            mkdir -p "$full_path"
            print_success "Created directory: $full_path"
        else
            print_status "Directory already exists: $full_path"
        fi
    done
}

# Function to create bootstrap/cache directory
create_bootstrap_cache() {
    print_status "Creating bootstrap cache directory..."
    
    local bootstrap_cache="$CURRENT_DIR/bootstrap/cache"
    if [ ! -d "$bootstrap_cache" ]; then
        mkdir -p "$bootstrap_cache"
        print_success "Created bootstrap cache directory: $bootstrap_cache"
    else
        print_status "Bootstrap cache directory already exists: $bootstrap_cache"
    fi
}

# Function to set proper permissions
set_storage_permissions() {
    print_status "Setting storage permissions..."
    
    # Check if we have permission to change ownership
    if [[ $EUID -eq 0 ]]; then
        # Running as root, set proper ownership
        chown -R www-data:www-data "$STORAGE_DIR"
        chown -R www-data:www-data "$CURRENT_DIR/bootstrap/cache"
        print_success "Set ownership to www-data:www-data"
    else
        print_warning "Not running as root. Skipping ownership changes."
        print_status "You may need to run: sudo chown -R www-data:www-data $STORAGE_DIR"
        print_status "And: sudo chown -R www-data:www-data $CURRENT_DIR/bootstrap/cache"
    fi
    
    # Set directory permissions (775 = rwxrwxr-x)
    chmod -R 775 "$STORAGE_DIR"
    chmod -R 775 "$CURRENT_DIR/bootstrap/cache"
    print_success "Set directory permissions to 775"
    
    # Set file permissions for any existing files (664 = rw-rw-r--)
    find "$STORAGE_DIR" -type f -exec chmod 664 {} \; 2>/dev/null || true
    find "$CURRENT_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; 2>/dev/null || true
    print_success "Set file permissions to 664"
}

# Function to clear Laravel caches
clear_laravel_caches() {
    print_status "Clearing Laravel caches..."
    
    # Check if artisan exists
    if [ ! -f "$CURRENT_DIR/artisan" ]; then
        print_warning "artisan file not found. Skipping cache clearing."
        return
    fi
    
    # Clear various caches
    local commands=(
        "config:clear"
        "route:clear"
        "view:clear"
        "cache:clear"
    )
    
    for cmd in "${commands[@]}"; do
        if php artisan "$cmd" >/dev/null 2>&1; then
            print_success "Cleared: $cmd"
        else
            print_warning "Failed to clear: $cmd (this may be normal)"
        fi
    done
    
    # Try to rebuild caches
    local rebuild_commands=(
        "config:cache"
        "route:cache"
        "view:cache"
    )
    
    for cmd in "${rebuild_commands[@]}"; do
        if php artisan "$cmd" >/dev/null 2>&1; then
            print_success "Rebuilt: $cmd"
        else
            print_warning "Failed to rebuild: $cmd"
        fi
    done
}

# Function to create .gitkeep files
create_gitkeep_files() {
    print_status "Creating .gitkeep files to preserve directory structure..."
    
    local dirs=(
        "$STORAGE_DIR/app"
        "$STORAGE_DIR/framework/cache/data"
        "$STORAGE_DIR/framework/sessions"
        "$STORAGE_DIR/framework/testing"
        "$STORAGE_DIR/framework/views"
        "$STORAGE_DIR/logs"
    )
    
    for dir in "${dirs[@]}"; do
        if [ -d "$dir" ] && [ ! -f "$dir/.gitkeep" ]; then
            touch "$dir/.gitkeep"
            print_success "Created .gitkeep in: $dir"
        fi
    done
}

# Function to verify the fix
verify_storage_setup() {
    print_status "Verifying storage setup..."
    
    local errors=0
    
    # Check required directories
    local required_dirs=(
        "$STORAGE_DIR"
        "$STORAGE_DIR/framework"
        "$STORAGE_DIR/framework/views"
        "$STORAGE_DIR/framework/cache"
        "$STORAGE_DIR/logs"
        "$CURRENT_DIR/bootstrap/cache"
    )
    
    for dir in "${required_dirs[@]}"; do
        if [ ! -d "$dir" ]; then
            print_error "Missing directory: $dir"
            ((errors++))
        elif [ ! -w "$dir" ]; then
            print_error "Directory not writable: $dir"
            ((errors++))
        fi
    done
    
    # Test Laravel if artisan is available
    if [ -f "$CURRENT_DIR/artisan" ]; then
        if php artisan --version >/dev/null 2>&1; then
            print_success "Laravel artisan is working"
        else
            print_error "Laravel artisan command failed"
            ((errors++))
        fi
    fi
    
    if [ $errors -eq 0 ]; then
        print_success "All verifications passed!"
        return 0
    else
        print_error "$errors verification(s) failed!"
        return 1
    fi
}

# Function to display summary
display_summary() {
    echo ""
    echo "📊 Laravel Storage Fix Summary:"
    echo "==============================="
    echo "📁 Storage Directory: $STORAGE_DIR"
    echo "📁 Bootstrap Cache: $CURRENT_DIR/bootstrap/cache"
    echo ""
    echo "🗂️  Created Directories:"
    echo "   • storage/app/"
    echo "   • storage/framework/cache/data/"
    echo "   • storage/framework/sessions/"
    echo "   • storage/framework/views/"
    echo "   • storage/logs/"
    echo "   • bootstrap/cache/"
    echo ""
    echo "🔐 Permissions Set:"
    echo "   • Directories: 775 (rwxrwxr-x)"
    echo "   • Files: 664 (rw-rw-r--)"
    echo "   • Owner: www-data:www-data (if run as root)"
    echo ""
    echo "✅ Laravel storage fix complete!"
    echo ""
    echo "📝 Next Steps:"
    echo "   1. Test your application in a browser"
    echo "   2. Check if the cache error is resolved"
    echo "   3. Monitor storage/logs/laravel.log for any new errors"
    echo ""
}

# Function to show manual commands if script fails
show_manual_commands() {
    echo ""
    echo "🔧 Manual Fix Commands (if needed):"
    echo "=================================="
    echo ""
    echo "# Create storage structure:"
    echo "mkdir -p storage/{app,framework/{cache/data,sessions,testing,views},logs}"
    echo "mkdir -p bootstrap/cache"
    echo ""
    echo "# Set permissions:"
    echo "chmod -R 775 storage bootstrap/cache"
    echo "sudo chown -R www-data:www-data storage bootstrap/cache"
    echo ""
    echo "# Clear caches:"
    echo "php artisan config:clear"
    echo "php artisan route:clear"
    echo "php artisan view:clear"
    echo "php artisan cache:clear"
    echo ""
}

# Main execution
main() {
    echo "🚀 Starting Laravel storage fix..."
    echo "=================================="
    echo "Working directory: $CURRENT_DIR"
    echo ""
    
    check_permissions
    create_storage_structure
    create_bootstrap_cache
    set_storage_permissions
    create_gitkeep_files
    clear_laravel_caches
    
    if verify_storage_setup; then
        display_summary
        print_success "Laravel storage fix completed successfully! 🎉"
    else
        print_error "Some verifications failed. Please check the output above."
        show_manual_commands
        exit 1
    fi
}

# Run main function
main "$@" 