#!/bin/bash
# Test script to verify the workflow update script fixes

set -e

echo "=============================================="
echo "🧪 Testing Workflow Update Script Fixes"
echo "=============================================="
echo ""

# Create a temporary test directory
TEST_DIR="/tmp/workflow-test-$$"
mkdir -p "$TEST_DIR"

# Create test workflow files with different indentation levels
cat > "$TEST_DIR/test-10-spaces.yml" << 'EOF'
name: Test 10 spaces
jobs:
  deploy:
    steps:
      - name: Deploy
        script: |
          echo "Deploying..."
          sudo systemctl restart apache2
          echo "Done"
EOF

cat > "$TEST_DIR/test-14-spaces.yml" << 'EOF'
name: Test 14 spaces
jobs:
  deploy:
    steps:
      - name: Deploy
        script: |
          echo "Deploying..."
              sudo systemctl restart apache2
          echo "Done"
EOF

cat > "$TEST_DIR/test-18-spaces.yml" << 'EOF'
name: Test 18 spaces
jobs:
  deploy:
    steps:
      - name: Deploy
        script: |
          echo "Deploying..."
                  sudo systemctl restart apache2
          echo "Done"
EOF

# Test the AWK logic on each file
echo "Testing Bug Fix #1: No extra newlines"
echo "Testing Bug Fix #2: Dynamic indentation matching"
echo ""

for TEST_FILE in "$TEST_DIR"/*.yml; do
    FILENAME=$(basename "$TEST_FILE")
    echo "📝 Processing: $FILENAME"
    
    # Apply the fixed AWK logic
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
    ' "$TEST_FILE" > "${TEST_FILE}.result"
    
    # Show the result
    echo "Result:"
    echo "----------------------------------------"
    cat "${TEST_FILE}.result" | grep -A 5 "Configure PHP settings"
    echo "----------------------------------------"
    echo ""
    
    # Check indentation level
    INDENT_LEVEL=$(grep "sudo systemctl restart apache2" "${TEST_FILE}.result" | head -1 | sed 's/\(^[ ]*\).*/\1/' | wc -c)
    PHP_CONFIG_INDENT=$(grep "Configure PHP settings" "${TEST_FILE}.result" | sed 's/\(^[ ]*\).*/\1/' | wc -c)
    
    if [ "$INDENT_LEVEL" -eq "$PHP_CONFIG_INDENT" ]; then
        echo "✅ Indentation matches: $INDENT_LEVEL spaces"
    else
        echo "❌ Indentation mismatch: Apache=$INDENT_LEVEL, PHP Config=$PHP_CONFIG_INDENT"
    fi
    echo ""
done

# Check for extra newlines
echo "Checking for extra newlines..."
for TEST_FILE in "$TEST_DIR"/*.result; do
    BLANK_LINES=$(grep -c "^$" "$TEST_FILE" || true)
    echo "  $(basename "$TEST_FILE"): $BLANK_LINES blank lines"
done

echo ""
echo "✅ Test complete. Check results above."
echo ""

# Cleanup
rm -rf "$TEST_DIR"
echo "🧹 Cleaned up test files"

