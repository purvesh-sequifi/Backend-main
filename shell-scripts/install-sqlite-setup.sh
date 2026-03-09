#!/usr/bin/env bash

set -e

echo "🔍 Checking SQLite setup requirements..."

# Get DOMAIN_NAME from .env or use default
if [ -f "/var/www/backend/current/.env" ]; then
    DOMAIN_NAME=$(grep "^DOMAIN_NAME=" /var/www/backend/current/.env 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "default")
else
    DOMAIN_NAME="default"
fi

echo "📌 Using DOMAIN_NAME: $DOMAIN_NAME"

# Database paths with DOMAIN_NAME prefix
DATABASE_PATH="/var/www/backend/databases/${DOMAIN_NAME}_database.sqlite"
API_METRICS_PATH="/var/www/backend/databases/${DOMAIN_NAME}_api_metrics.sqlite"

echo "📌 Database paths:"
echo "  - $DATABASE_PATH"
echo "  - $API_METRICS_PATH"

# Check if SQLite databases already exist
DATABASE_EXISTS=false
API_METRICS_EXISTS=false

if [ -f "$DATABASE_PATH" ]; then
    echo "✅ Main SQLite database already exists"
    DATABASE_EXISTS=true
fi

if [ -f "$API_METRICS_PATH" ]; then
    echo "✅ API Metrics SQLite database already exists"
    API_METRICS_EXISTS=true
fi

# If both databases exist, skip setup
if [ "$DATABASE_EXISTS" = true ] && [ "$API_METRICS_EXISTS" = true ]; then
    echo "✅ All SQLite databases already configured, skipping setup"
    exit 0
fi

echo "📦 SQLite databases need setup, proceeding..."

# Install SQLite extension if not present
if ! php -m | grep -q sqlite3; then
    echo "📦 Installing php-sqlite3..."
    
    echo "Reconfiguring dpkg (repair packages)..."
    sudo dpkg --configure -a
    
    echo "Fixing broken installs..."
    sudo apt-get install -f -y
    
    echo "Updating package cache..."
    sudo apt-get update -y
    
    echo "Installing sqlite3..."
    sudo apt-get install php-sqlite3 -y
else
    echo "✅ php-sqlite3 already installed"
fi

# Ensure directory exists
echo "📁 Ensuring SQLite databases directory exists..."
sudo mkdir -p /var/www/backend/databases

# Create database files if they don't exist
if [ "$DATABASE_EXISTS" = false ]; then
    echo "🔧 Creating main SQLite database at $DATABASE_PATH..."
    sudo touch "$DATABASE_PATH"
    sudo chmod 666 "$DATABASE_PATH"
    echo "✅ Main SQLite database created"
fi

if [ "$API_METRICS_EXISTS" = false ]; then
    echo "🔧 Creating API Metrics SQLite database at $API_METRICS_PATH..."
    sudo touch "$API_METRICS_PATH"
    sudo chmod 666 "$API_METRICS_PATH"
    echo "✅ API Metrics SQLite database created"
fi

# Set proper permissions
sudo chmod 777 /var/www/backend/databases
sudo chown -R www-data:www-data /var/www/backend/databases

# Run API metrics migration if database was just created
if [ "$API_METRICS_EXISTS" = false ]; then
    echo "📊 Running API metrics migration..."
    cd /var/www/backend/current
    
    # Check if migration file exists
    if [ -f "database/migrations/2024_01_01_000000_create_api_metrics_tables.php" ]; then
        php artisan migrate --path=database/migrations/2024_01_01_000000_create_api_metrics_tables.php --database=api_metrics --force
        echo "✅ API metrics migration completed"
    else
        echo "⚠️  API metrics migration file not found, skipping"
    fi
fi

echo "✅ SQLite setup completed successfully!"
