#!/bin/bash

# Sequifi DEFINER Issues Fix Script
# This script automatically fixes all DEFINER issues during deployment
# Usage: ./scripts/fix-definer-issues.sh [--dry-run] [--force]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default options
DRY_RUN=false
FORCE=false
BACKUP=true

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --no-backup)
            BACKUP=false
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [--dry-run] [--force] [--no-backup]"
            echo "  --dry-run    Show what would be changed without making changes"
            echo "  --force      Skip confirmation prompts"
            echo "  --no-backup  Skip creating backup before changes"
            exit 0
            ;;
        *)
            echo "Unknown option $1"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}🔧 Sequifi DEFINER Issues Fix Script${NC}"
echo -e "${BLUE}====================================${NC}"

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: artisan file not found. Please run this script from the Laravel root directory.${NC}"
    exit 1
fi

# Build command options
CMD_OPTIONS=""
if [ "$DRY_RUN" = true ]; then
    CMD_OPTIONS="$CMD_OPTIONS --dry-run"
fi
if [ "$FORCE" = true ]; then
    CMD_OPTIONS="$CMD_OPTIONS --force"
fi
if [ "$BACKUP" = true ]; then
    CMD_OPTIONS="$CMD_OPTIONS --backup"
fi

echo -e "${YELLOW}🔍 Running DEFINER fix with options:${CMD_OPTIONS}${NC}"

# Run the Laravel command
php artisan db:fix-definers $CMD_OPTIONS

if [ "$DRY_RUN" = false ]; then
    echo -e "${GREEN}✅ DEFINER fix completed successfully!${NC}"
    echo -e "${BLUE}📋 Next steps:${NC}"
    echo -e "   1. Test your application to ensure everything works"
    echo -e "   2. Deploy this fix to other servers"
    echo -e "   3. Add this script to your deployment pipeline"
else
    echo -e "${YELLOW}📋 Dry run completed. Use --force to apply changes.${NC}"
fi

echo -e "${BLUE}🔍 To verify the fix worked, run:${NC}"
echo -e "   php artisan db:list-triggers"
