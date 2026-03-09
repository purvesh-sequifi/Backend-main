#!/bin/bash

# ============================================================================
# AWS Parameter Store Update Script for backend/adam
# ============================================================================
# This script updates all Octane + Horizon + Redis environment variables
# in AWS Systems Manager Parameter Store
#
# Usage:
#   bash scripts/update-aws-parameter-store-adam.sh
#
# Prerequisites:
#   - AWS CLI installed and configured
#   - IAM permissions to write to Parameter Store
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PARAMETER_PREFIX="/backend/adam"
AWS_REGION="${AWS_REGION:-us-east-1}"

echo -e "${BLUE}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║   AWS Parameter Store Update - backend/adam               ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${YELLOW}AWS Region: ${AWS_REGION}${NC}"
echo -e "${YELLOW}Parameter Prefix: ${PARAMETER_PREFIX}${NC}"
echo ""

# Check if AWS CLI is installed
if ! command -v aws &> /dev/null; then
    echo -e "${RED}❌ ERROR: AWS CLI is not installed${NC}"
    echo "Install: https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html"
    exit 1
fi

# Check if AWS credentials are configured
if ! aws sts get-caller-identity &> /dev/null; then
    echo -e "${RED}❌ ERROR: AWS credentials not configured${NC}"
    echo "Run: aws configure"
    exit 1
fi

echo -e "${GREEN}✓ AWS CLI installed and configured${NC}"
echo ""

# Counter
SUCCESS=0
FAILED=0

# Function to update parameter
update_parameter() {
    local name="$1"
    local value="$2"
    local full_path="${PARAMETER_PREFIX}/${name}"
    
    echo -n "Updating ${name}... "
    
    if aws ssm put-parameter \
        --name "${full_path}" \
        --value "${value}" \
        --type "String" \
        --overwrite \
        --region "${AWS_REGION}" \
        &> /dev/null; then
        echo -e "${GREEN}✓${NC}"
        ((SUCCESS++))
    else
        echo -e "${RED}✗${NC}"
        ((FAILED++))
    fi
}

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Queue Configuration...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "QUEUE_CONNECTION" "redis"
update_parameter "AWS_WEBHOOK_QUEUE_CONNECTION" "redis"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Cache & Session Configuration...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "CACHE_DRIVER" "redis"
update_parameter "SESSION_DRIVER" "redis"
update_parameter "SESSION_CONNECTION" ""
update_parameter "SESSION_STORE" ""

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Redis Configuration...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "REDIS_CLIENT" "predis"
update_parameter "REDIS_HOST" "127.0.0.1"
update_parameter "REDIS_USERNAME" ""
update_parameter "REDIS_PASSWORD" ""
update_parameter "REDIS_PORT" "6379"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Redis Database Separation...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "REDIS_DB" "0"
update_parameter "REDIS_CACHE_DB" "1"
update_parameter "REDIS_QUEUE_DB" "2"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Redis Queue Connection...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "REDIS_QUEUE_CONNECTION" "queue"
update_parameter "REDIS_QUEUE" "default"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Redis Clustering...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "REDIS_CLUSTER" "redis"
update_parameter "REDIS_PREFIX" "adam_database_"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Octane Configuration...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "OCTANE_SERVER" "swoole"
update_parameter "SWOOLE_WORKERS" "16"
update_parameter "SWOOLE_TASK_WORKERS" "8"
update_parameter "SWOOLE_MAX_REQUEST" "1000"
update_parameter "SWOOLE_MAX_WAIT_TIME" "60"
update_parameter "SWOOLE_LOG_LEVEL" "2"
update_parameter "OCTANE_HTTPS" "false"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Horizon Configuration...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "HORIZON_NAME" "adam_horizon"
update_parameter "HORIZON_DOMAIN" ""
update_parameter "HORIZON_PATH" "horizon"
update_parameter "HORIZON_PREFIX" "adam_horizon:"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Updating Garbage Collection...${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

update_parameter "OCTANE_GC_ENABLED" "true"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Summary${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${GREEN}✓ Successfully updated: ${SUCCESS} parameters${NC}"
if [ $FAILED -gt 0 ]; then
    echo -e "${RED}✗ Failed to update: ${FAILED} parameters${NC}"
fi
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}╔════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  ✓ ALL PARAMETERS UPDATED SUCCESSFULLY!       ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Next steps:${NC}"
    echo "1. The parameters are now in AWS Parameter Store"
    echo "2. Your GitHub Actions workflow will pull these values"
    echo "3. Deploy your application to apply the changes"
    echo ""
    exit 0
else
    echo -e "${RED}╔════════════════════════════════════════════════╗${NC}"
    echo -e "${RED}║  ✗ SOME PARAMETERS FAILED TO UPDATE           ║${NC}"
    echo -e "${RED}╚════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}Troubleshooting:${NC}"
    echo "1. Check your IAM permissions for SSM Parameter Store"
    echo "2. Verify AWS credentials: aws sts get-caller-identity"
    echo "3. Check AWS region: export AWS_REGION=your-region"
    echo ""
    exit 1
fi

