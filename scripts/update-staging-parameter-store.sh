#!/bin/bash

# ============================================================================
# Update 5 Staging Parameter Store Entries with Octane/Horizon/Redis Variables
# ============================================================================
# This script adds 30 new environment variables to each staging server's
# Parameter Store entry
#
# Usage: bash scripts/update-staging-parameter-store.sh
# ============================================================================

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║   Update Staging Parameter Store - Octane Variables       ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# Check AWS CLI
if ! command -v aws &> /dev/null; then
    echo -e "${RED}❌ AWS CLI not installed${NC}"
    exit 1
fi

# Staging servers
STAGING_SERVERS=(
    "solarstage"
    "turfstage"
    "fiberstage"
    "peststage"
    "mortgagestage"
)

SUCCESS_COUNT=0
FAILED_COUNT=0

# Function to update one server's Parameter Store
update_server_params() {
    local server_name=$1
    local param_path="/backend/${server_name}"
    
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Updating: ${server_name}${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    # Get existing parameter
    echo "📥 Fetching existing parameter..."
    if ! aws ssm get-parameter --name "$param_path" --with-decryption --query "Parameter.Value" --output text > /tmp/${server_name}_existing.txt 2>/dev/null; then
        echo -e "${RED}❌ Parameter $param_path not found${NC}"
        ((FAILED_COUNT++))
        return 1
    fi
    
    EXISTING_LINES=$(wc -l < /tmp/${server_name}_existing.txt)
    echo -e "${GREEN}✓ Got existing parameter ($EXISTING_LINES variables)${NC}"
    
    # Check if Octane variables already exist
    if grep -q "OCTANE_SERVER" /tmp/${server_name}_existing.txt; then
        echo -e "${YELLOW}⚠️  Octane variables already exist, will replace them${NC}"
        # Remove existing Octane section
        sed -i '/^QUEUE_CONNECTION=redis$/,/^OCTANE_GC_ENABLED=/d' /tmp/${server_name}_existing.txt
        sed -i '/^DB_PERSISTENT=/d' /tmp/${server_name}_existing.txt
    fi
    
    # Append new Octane/Horizon/Redis variables
    cat >> /tmp/${server_name}_existing.txt << EOF

# ============================================================================
# OCTANE + HORIZON + REDIS CONFIGURATION (Added $(date +%Y-%m-%d))
# ============================================================================

# Queue Configuration
QUEUE_CONNECTION=redis
AWS_WEBHOOK_QUEUE_CONNECTION=redis

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=
SESSION_STORE=

# Redis Server Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_USERNAME=
REDIS_PASSWORD=
REDIS_PORT=6379

# Redis Database Separation
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2

# Redis Queue Connection
REDIS_QUEUE_CONNECTION=queue
REDIS_QUEUE=default

# Redis Clustering
REDIS_CLUSTER=redis
REDIS_PREFIX=${server_name}_database_

# Octane Configuration
OCTANE_SERVER=swoole
SWOOLE_WORKERS=24
SWOOLE_TASK_WORKERS=12
SWOOLE_MAX_REQUEST=5000
SWOOLE_MAX_WAIT_TIME=60
SWOOLE_LOG_LEVEL=2
OCTANE_HTTPS=false

# Horizon Configuration
HORIZON_NAME=${server_name}_horizon
HORIZON_DOMAIN=
HORIZON_PATH=horizon
HORIZON_PREFIX=${server_name}_horizon:

# Garbage Collection & Optimizations
OCTANE_GC_ENABLED=true
DB_PERSISTENT=true
EOF
    
    NEW_LINES=$(wc -l < /tmp/${server_name}_existing.txt)
    ADDED_LINES=$((NEW_LINES - EXISTING_LINES))
    echo -e "${GREEN}✓ Added $ADDED_LINES new variables${NC}"
    
    # Update Parameter Store (use Advanced tier for large parameters)
    echo "📤 Updating Parameter Store..."
    if aws ssm put-parameter \
        --name "$param_path" \
        --value file:///tmp/${server_name}_existing.txt \
        --type "SecureString" \
        --tier "Advanced" \
        --overwrite \
        --region us-east-1 > /dev/null 2>&1; then
        
        # Verify update
        VERIFY_LINES=$(aws ssm get-parameter --name "$param_path" --with-decryption --query "Parameter.Value" --output text 2>/dev/null | wc -l)
        
        echo -e "${GREEN}✓ Parameter Store updated successfully${NC}"
        echo -e "${GREEN}  Total variables: $VERIFY_LINES${NC}"
        ((SUCCESS_COUNT++))
        
        # Cleanup
        rm -f /tmp/${server_name}_existing.txt
        
        return 0
    else
        echo -e "${RED}❌ Failed to update Parameter Store${NC}"
        ((FAILED_COUNT++))
        return 1
    fi
}

# Update all staging servers
echo ""
for server in "${STAGING_SERVERS[@]}"; do
    update_server_params "$server"
    echo ""
done

# Summary
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}SUMMARY${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}✓ Successfully updated: $SUCCESS_COUNT servers${NC}"
if [ $FAILED_COUNT -gt 0 ]; then
    echo -e "${RED}✗ Failed to update: $FAILED_COUNT servers${NC}"
fi
echo ""

if [ $FAILED_COUNT -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ ALL STAGING PARAMETER STORES UPDATED!      ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Review the changes"
    echo "2. Create master deployment script"
    echo "3. Update 5 staging workflow files"
    exit 0
else
    echo -e "${RED}╔════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ SOME UPDATES FAILED                        ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════╝${NC}"
    exit 1
fi

