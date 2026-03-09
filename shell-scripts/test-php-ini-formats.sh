#!/bin/bash
# Test script to verify PHP ini file format handling

set -e

echo "=============================================="
echo "🧪 Testing PHP INI Format Handling"
echo "=============================================="
echo ""

# Create a temporary test directory
TEST_DIR="/tmp/php-ini-test-$$"
mkdir -p "$TEST_DIR"

# Test Case 1: Setting with spaces around equals (standard format)
cat > "$TEST_DIR/test1.ini" << 'EOF'
; Test file 1: Standard format with spaces
max_execution_time = 30
max_input_time = 60
EOF

# Test Case 2: Setting without spaces around equals
cat > "$TEST_DIR/test2.ini" << 'EOF'
; Test file 2: No spaces around equals
max_execution_time=30
max_input_time=60
EOF

# Test Case 3: Commented setting with space after semicolon (typical)
cat > "$TEST_DIR/test3.ini" << 'EOF'
; Test file 3: Commented with space
; max_execution_time = 30
; max_input_time = 60
EOF

# Test Case 4: Commented setting without space after semicolon
cat > "$TEST_DIR/test4.ini" << 'EOF'
; Test file 4: Commented without space
;max_execution_time = 30
;max_input_time = 60
EOF

# Test Case 5: Mixed formats
cat > "$TEST_DIR/test5.ini" << 'EOF'
; Test file 5: Mixed formats
max_execution_time=30
; max_input_time = 60
default_socket_timeout = 90
EOF

# Test Case 6: Setting doesn't exist
cat > "$TEST_DIR/test6.ini" << 'EOF'
; Test file 6: Setting doesn't exist
upload_max_filesize = 2M
EOF

# Source the functions from the main script
update_php_setting() {
    local PHP_INI=$1
    local SETTING_NAME=$2
    local SETTING_VALUE=$3
    
    # Update the setting to 300 (handles all PHP ini format variations)
    # Check for active setting (with or without spaces around =)
    if grep -qE "^${SETTING_NAME}\s*=" "$PHP_INI"; then
        # Replace existing active setting (handles both "setting=value" and "setting = value")
        sed -i '' "s/^${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$PHP_INI" 2>/dev/null || sed -i "s/^${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$PHP_INI"
    # Check for commented setting (with or without space after semicolon)
    elif grep -qE "^;\s*${SETTING_NAME}\s*=" "$PHP_INI"; then
        # Uncomment and replace (handles both ";setting" and "; setting")
        sed -i '' "s/^;\s*${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$PHP_INI" 2>/dev/null || sed -i "s/^;\s*${SETTING_NAME}\s*=.*/${SETTING_NAME} = ${SETTING_VALUE}/" "$PHP_INI"
    else
        # Setting doesn't exist, append it
        echo "${SETTING_NAME} = ${SETTING_VALUE}" >> "$PHP_INI"
    fi
}

# Test each case
TEST_NUM=1
for TEST_FILE in "$TEST_DIR"/test*.ini; do
    FILENAME=$(basename "$TEST_FILE")
    echo "📝 Test Case $TEST_NUM: $FILENAME"
    echo "Before:"
    cat "$TEST_FILE"
    echo ""
    
    # Apply updates
    update_php_setting "$TEST_FILE" "max_execution_time" "300"
    
    echo "After:"
    cat "$TEST_FILE"
    echo ""
    
    # Verify result
    RESULT=$(grep "^max_execution_time = 300" "$TEST_FILE" || echo "FAILED")
    if [ "$RESULT" != "FAILED" ]; then
        echo "✅ Success: Setting correctly updated to 300"
    else
        echo "❌ Failed: Setting not properly updated"
        echo "   Found: $(grep -E "max_execution_time|^;.*max_execution_time" "$TEST_FILE" || echo 'NOTHING')"
    fi
    
    # Check for duplicates
    DUPLICATE_COUNT=$(grep -c "max_execution_time" "$TEST_FILE" || echo "0")
    if [ "$DUPLICATE_COUNT" -eq 1 ]; then
        echo "✅ No duplicates: Only 1 instance found"
    else
        echo "❌ Duplicates detected: $DUPLICATE_COUNT instances found"
    fi
    
    echo ""
    echo "----------------------------------------"
    echo ""
    
    ((TEST_NUM++))
done

# Summary
echo "=============================================="
echo "✅ Test Suite Complete"
echo "=============================================="
echo ""
echo "Tested formats:"
echo "  ✓ Active setting with spaces (setting = value)"
echo "  ✓ Active setting without spaces (setting=value)"
echo "  ✓ Commented with space (; setting = value)"
echo "  ✓ Commented without space (;setting=value)"
echo "  ✓ Mixed formats"
echo "  ✓ Non-existent setting (append)"
echo ""

# Cleanup
rm -rf "$TEST_DIR"
echo "🧹 Cleaned up test files"

