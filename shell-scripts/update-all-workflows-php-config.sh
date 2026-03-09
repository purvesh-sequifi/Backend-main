#!/bin/bash
# Script to add PHP configuration step to all deployment workflows
# This ensures max_execution_time is set to 300 seconds on all servers

set -e

WORKFLOW_DIR="/opt/homebrew/var/www/Backend/.github/workflows"
BACKUP_DIR="/opt/homebrew/var/www/Backend/.github/workflows/backups-$(date +%Y%m%d-%H%M%S)"

echo "=============================================="
echo "🔧 Adding PHP Configuration to All Workflows"
echo "=============================================="
echo ""

# Create backup directory
mkdir -p "$BACKUP_DIR"
echo "✅ Created backup directory: $BACKUP_DIR"
echo ""

# Counter for updated files
UPDATED_COUNT=0
SKIPPED_COUNT=0
TOTAL_COUNT=0

# Find all workflow files (excluding Archived directory and specific files)
cd "$WORKFLOW_DIR"
for WORKFLOW_FILE in *.yml *.yaml; do
    # Skip if file doesn't exist or is in archived
    if [ ! -f "$WORKFLOW_FILE" ] || [[ "$WORKFLOW_FILE" == "Archived"* ]]; then
        continue
    fi
    
    # Skip non-deployment workflows
    if [[ "$WORKFLOW_FILE" == "code-quality.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "ai-pr-review-advanced.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "pr-checks-minimal.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "generate-changelog.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "auto-sync-main-to-octane-feature.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "back-merge-to-uat-stg-release.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "laravel-10-compatibility.yml" ]] || \
       [[ "$WORKFLOW_FILE" == "install-php83-all-servers.yml" ]]; then
        echo "⏭️  Skipping non-deployment workflow: $WORKFLOW_FILE"
        ((SKIPPED_COUNT++))
        continue
    fi
    
    ((TOTAL_COUNT++))
    
    # Create backup
    cp "$WORKFLOW_FILE" "$BACKUP_DIR/$WORKFLOW_FILE"
    
    # Check if PHP config already exists
    if grep -q "configure-php-settings.sh" "$WORKFLOW_FILE"; then
        echo "✅ Already configured: $WORKFLOW_FILE"
        ((SKIPPED_COUNT++))
        continue
    fi
    
    # Check if this is a deployment workflow with Apache restart
    if grep -q "sudo systemctl restart apache2" "$WORKFLOW_FILE" || \
       grep -q "sudo service apache2 restart" "$WORKFLOW_FILE"; then
        
        echo "📝 Updating: $WORKFLOW_FILE"
        
        # Add PHP configuration before Apache restart
        # Dynamically detect indentation and insert snippet with correct spacing
        awk '
            /sudo systemctl restart apache2/ || /sudo service apache2 restart/ {
                if (!config_added) {
                    # Capture the leading whitespace (indentation) from the current line
                    match($0, /^[ \t]*/)
                    indent = substr($0, RSTART, RLENGTH)
                    
                    # Print PHP config snippet with proper indentation (no trailing newline)
                    printf "%s# Configure PHP settings (max_execution_time = 300s)\n", indent
                    printf "%secho \"🔧 Configuring PHP settings...\"\n", indent
                    printf "%ssudo bash \"${DEPLOY_DIR}/shell-scripts/configure-php-settings.sh\" || echo \"⚠️ PHP configuration script not found, skipping\"\n", indent
                    printf "\n"
                    
                    config_added = 1
                }
            }
            { print }
        ' "$WORKFLOW_FILE" > "${WORKFLOW_FILE}.tmp"
        
        mv "${WORKFLOW_FILE}.tmp" "$WORKFLOW_FILE"
        ((UPDATED_COUNT++))
        echo "✅ Updated: $WORKFLOW_FILE"
    else
        echo "⏭️  Skipping (no Apache restart found): $WORKFLOW_FILE"
        ((SKIPPED_COUNT++))
    fi
    
    echo ""
done

echo "=============================================="
echo "✅ Workflow Update Complete"
echo "=============================================="
echo ""
echo "Summary:"
echo "  📊 Total deployment workflows: $TOTAL_COUNT"
echo "  ✅ Updated workflows: $UPDATED_COUNT"
echo "  ⏭️  Skipped workflows: $SKIPPED_COUNT"
echo ""
echo "📁 Backups saved to: $BACKUP_DIR"
echo ""
echo "⚠️  IMPORTANT: Review changes and commit to Git"
echo "=============================================="

