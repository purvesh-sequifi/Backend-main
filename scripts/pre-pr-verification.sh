#!/bin/bash

# Pre-PR Verification Script for Sequifi Laravel Project
# This script automates common pre-PR checklist items

echo "🚀 Starting Pre-PR Verification for Sequifi..."
echo "=================================================="

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Track overall success
OVERALL_SUCCESS=true

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
        OVERALL_SUCCESS=false
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_non_blocking() {
    echo -e "${YELLOW}⚠️  $1 (informational only)${NC}"
}

# 1. Check if we're in a git repository
echo -e "\n${BLUE}1. Checking Git Repository...${NC}"
if git rev-parse --git-dir > /dev/null 2>&1; then
    print_status 0 "Git repository detected"
    
    # Check if we're on a feature branch
    CURRENT_BRANCH=$(git branch --show-current)
    if [[ "$CURRENT_BRANCH" == "main" ]] || [[ "$CURRENT_BRANCH" == "master" ]] || [[ "$CURRENT_BRANCH" == "develop" ]]; then
        print_warning "You're on the main branch ($CURRENT_BRANCH). Consider creating a feature branch."
    else
        print_status 0 "On feature branch: $CURRENT_BRANCH"
    fi
else
    print_status 1 "Not in a git repository"
    exit 1
fi

# 2. Check for Laravel project
echo -e "\n${BLUE}2. Checking Laravel Project...${NC}"
if [ -f "artisan" ] && [ -f "composer.json" ]; then
    print_status 0 "Laravel project detected"
else
    print_status 1 "Laravel project not detected (missing artisan or composer.json)"
    exit 1
fi

# 3. Update from main branch
echo -e "\n${BLUE}3. Updating from main branch...${NC}"
print_info "Fetching latest changes..."
# Check if we're in CI environment
if [ -n "$GITHUB_ACTIONS" ] || [ -n "$CI" ]; then
    print_info "Running in CI environment - skipping interactive prompts"
    print_status 0 "Branch comparison will use available refs"
else
    git fetch origin > /dev/null 2>&1
    if git merge-base --is-ancestor origin/main HEAD; then
        print_status 0 "Branch is up to date with main"
    else
        print_warning "Branch is behind main. Consider merging latest changes."
        read -p "Do you want to merge main into current branch? (y/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            git merge origin/main
            if [ $? -eq 0 ]; then
                print_status 0 "Successfully merged main"
            else
                print_status 1 "Merge conflicts detected. Resolve conflicts first."
                exit 1
            fi
        fi
    fi
fi

# 4. Check for debug statements in changed files only
echo -e "\n${BLUE}4. Checking for debug statements in changed files...${NC}"
DEBUG_FOUND=false

# Get list of changed files in current branch vs main + unstaged changes
COMMITTED_FILES=$(git diff --name-only origin/main...HEAD 2>/dev/null || git diff --name-only HEAD~1...HEAD 2>/dev/null || echo "")
UNSTAGED_FILES=$(git diff --name-only 2>/dev/null || echo "")
CHANGED_FILES=$(echo -e "$COMMITTED_FILES\n$UNSTAGED_FILES" | sort -u | grep -v "^$" || echo "")

if [ -n "$CHANGED_FILES" ]; then
    # Check for debug statements only in changed PHP files
    CHANGED_PHP_FILES=$(echo "$CHANGED_FILES" | grep "\.php$" || echo "")
    if [ -n "$CHANGED_PHP_FILES" ]; then
        for file in $CHANGED_PHP_FILES; do
            if [ -f "$file" ]; then
                if grep -q "dd(" "$file" 2>/dev/null; then
                    print_status 1 "Found dd() in changed file: $file"
                    DEBUG_FOUND=true
                fi
                if grep -q "var_dump\|print_r" "$file" 2>/dev/null; then
                    print_status 1 "Found var_dump/print_r in changed file: $file"
                    DEBUG_FOUND=true
                fi
            fi
        done
    fi

    # Check for console statements only in changed JS/Vue files
    CHANGED_JS_FILES=$(echo "$CHANGED_FILES" | grep -E "\.(js|vue)$" || echo "")
    if [ -n "$CHANGED_JS_FILES" ]; then
        for file in $CHANGED_JS_FILES; do
            if [ -f "$file" ] && ! echo "$file" | grep -q "vendor\|node_modules\|public/demo"; then
                if grep -q "console\.log\|console\.error" "$file" 2>/dev/null; then
                    print_status 1 "Found console statements in changed file: $file"
                    DEBUG_FOUND=true
                fi
            fi
        done
    fi

    if [ "$DEBUG_FOUND" = false ]; then
        print_status 0 "No debug statements found in changed files"
    fi
else
    print_warning "No changed files detected or unable to compare with main branch"
fi

# 5. Check for TODO comments in changed files
echo -e "\n${BLUE}5. Checking for TODO comments in changed files...${NC}"
TODO_FOUND=false

if [ -n "$CHANGED_FILES" ]; then
    CHANGED_PHP_FILES=$(echo "$CHANGED_FILES" | grep "\.php$" || echo "")
    if [ -n "$CHANGED_PHP_FILES" ]; then
        for file in $CHANGED_PHP_FILES; do
            if [ -f "$file" ]; then
                if grep -q "TODO\|FIXME\|HACK" "$file" 2>/dev/null; then
                    print_warning "Found TODO/FIXME in changed file: $file"
                    TODO_FOUND=true
                fi
            fi
        done
    fi
fi

if [ "$TODO_FOUND" = false ]; then
    print_status 0 "No TODO comments found in changed files"
fi

# 6. Run Composer validation
echo -e "\n${BLUE}6. Validating Composer...${NC}"
if command -v composer > /dev/null 2>&1; then
    composer validate --no-check-all --no-check-publish > /dev/null 2>&1
    print_status $? "Composer validation"
else
    print_warning "Composer not found in PATH"
fi

# 7. Check for .env in git
echo -e "\n${BLUE}7. Checking for sensitive files in git...${NC}"
SENSITIVE_FILES_FOUND=false

if git ls-files | grep -E "\.env$|\.env\..*" > /dev/null 2>&1; then
    print_status 1 "Found .env files in git repository"
    echo "   Sensitive files:"
    git ls-files | grep -E "\.env$|\.env\..*" | sed 's/^/   - /'
    SENSITIVE_FILES_FOUND=true
fi

if git ls-files | grep -E "storage/.*\.log$|\.DS_Store$" > /dev/null 2>&1; then
    print_status 1 "Found log files or system files in git"
    echo "   System files:"
    git ls-files | grep -E "storage/.*\.log$|\.DS_Store$" | sed 's/^/   - /'
    SENSITIVE_FILES_FOUND=true
fi

if [ "$SENSITIVE_FILES_FOUND" = false ]; then
    print_status 0 "No sensitive files found in git"
fi

# 8. Check PHP syntax in changed files
echo -e "\n${BLUE}8. Checking PHP syntax in changed files...${NC}"
SYNTAX_ERROR_FOUND=false

if [ -n "$CHANGED_FILES" ]; then
    CHANGED_PHP_FILES=$(echo "$CHANGED_FILES" | grep "\.php$" || echo "")
    if [ -n "$CHANGED_PHP_FILES" ]; then
        for file in $CHANGED_PHP_FILES; do
            if [ -f "$file" ]; then
                SYNTAX_CHECK=$(php -l "$file" 2>&1)
                if [ $? -ne 0 ]; then
                    print_status 1 "Syntax error in: $file"
                    echo "$SYNTAX_CHECK" | sed 's/^/   /'
                    SYNTAX_ERROR_FOUND=true
                fi
            fi
        done
        
        if [ "$SYNTAX_ERROR_FOUND" = false ]; then
            print_status 0 "No syntax errors in changed PHP files"
        fi
    else
        print_info "No PHP files changed - skipping syntax check"
    fi
else
    print_info "No changed files detected - skipping syntax check"
fi

# 9. Run Laravel tests
echo -e "\n${BLUE}9. Running Laravel tests...${NC}"
if [ -f "vendor/bin/phpunit" ] || command -v php > /dev/null 2>&1; then
    print_info "Running tests... (this may take a moment)"
    php artisan test --stop-on-failure > /tmp/test_output 2>&1
    TEST_RESULT=$?
    
    # Check if there were actually tests to run
    if grep -q "No tests executed!" /tmp/test_output 2>/dev/null; then
        print_warning "No tests found - consider adding tests for your changes"
        TEST_RESULT=0  # Don't fail the script for missing tests, just warn
    elif [ $TEST_RESULT -eq 0 ]; then
        print_status 0 "All tests passed"
    else
        print_status 1 "Tests failed"
        echo "   Test output:"
        tail -20 /tmp/test_output | sed 's/^/   /'
    fi
else
    print_warning "Cannot run tests - PHP or PHPUnit not available"
fi

# 10. Check migrations
echo -e "\n${BLUE}10. Checking database migrations...${NC}"
if php artisan migrate:status > /dev/null 2>&1; then
    PENDING_MIGRATIONS=$(php artisan migrate:status | grep -c "Pending")
    if [ "$PENDING_MIGRATIONS" -gt 0 ]; then
        print_warning "$PENDING_MIGRATIONS pending migrations found"
        print_info "Consider running: php artisan migrate"
    else
        print_status 0 "All migrations up to date"
    fi
else
    print_warning "Cannot check migration status (database not configured?)"
fi

# 10. Clear caches
echo -e "\n${BLUE}11. Clearing Laravel caches...${NC}"
php artisan cache:clear > /dev/null 2>&1
print_status $? "Cache cleared"

php artisan config:clear > /dev/null 2>&1
print_status $? "Config cache cleared"

php artisan route:clear > /dev/null 2>&1
print_status $? "Route cache cleared"

php artisan view:clear > /dev/null 2>&1
print_status $? "View cache cleared"

# 12. Check for large files (informational only - not blocking)
echo -e "\n${BLUE}12. Checking for large files...${NC}"
LARGE_FILES=$(find . -name "*.php" -o -name "*.js" -o -name "*.css" -o -name "*.vue" | xargs wc -l 2>/dev/null | awk '$1 > 1000 {print $2 " (" $1 " lines)"}' | grep -v "total")

if [ -n "$LARGE_FILES" ]; then
    print_non_blocking "Found large files (>1000 lines) - consider refactoring"
    echo "$LARGE_FILES" | sed 's/^/   - /'
    print_info "This is not a blocking issue - just a suggestion for future refactoring"
else
    print_status 0 "No excessively large files found"
fi

# 13. Check commit messages
echo -e "\n${BLUE}13. Checking recent commit messages...${NC}"
RECENT_COMMITS=$(git log --oneline -5 --pretty=format:"%s")
if echo "$RECENT_COMMITS" | grep -qE "^(feat|fix|docs|style|refactor|test|chore)(\(.+\))?:" ; then
    print_status 0 "Recent commits follow conventional format"
else
    print_warning "Consider using conventional commit messages (feat:, fix:, docs:, etc.)"
fi

# Summary
echo -e "\n${BLUE}=================================================="
echo "📋 PRE-PR VERIFICATION SUMMARY"
echo "==================================================${NC}"

# Count only actual blocking issues (not warnings)
BLOCKING_ISSUES=false

# Check if we had any actual failures (not warnings)
if [ "$DEBUG_FOUND" = true ] || [ "$TEST_RESULT" -ne 0 ] || [ "$SENSITIVE_FILES_FOUND" = true ] || [ "$SYNTAX_ERROR_FOUND" = true ]; then
    BLOCKING_ISSUES=true
fi

if [ "$BLOCKING_ISSUES" = false ]; then
    echo -e "${GREEN}🎉 All critical checks passed!${NC}"
    echo -e "${GREEN}✅ Your code is ready for PR creation${NC}"
    if [ -n "$LARGE_FILES" ]; then
        echo -e "${BLUE}ℹ️  Note: Large files detected are informational only${NC}"
    fi
else
    echo -e "${RED}❌ Critical issues found that must be fixed${NC}"
    echo -e "${YELLOW}⚠️  Please address the blocking issues above before creating PR${NC}"
    echo -e "${BLUE}ℹ️  Warnings (like large files) are informational only${NC}"
fi

echo -e "\n${BLUE}📚 Manual Checklist Reminders:${NC}"
echo "   □ Code follows PSR-12 standards"
echo "   □ Meaningful variable/method names used"
echo "   □ No duplicate code (DRY principle)"
echo "   □ Input validation implemented"
echo "   □ Proper error handling added"
echo "   □ Authentication/authorization checks"
echo "   □ Database indexes added (if needed)"
echo "   □ Integration testing completed"
echo "   □ Documentation updated"

echo -e "\n${BLUE}📖 Next Steps:${NC}"
echo "   1. Address any issues found above"
echo "   2. Complete manual checklist items"
echo "   3. Create PR using the template"
echo "   4. Reference: docs/PRE_PR_DEVELOPER_CHECKLIST.md"

echo -e "\n${BLUE}🚀 Ready to create PR? Use:${NC}"
echo "   git push origin $CURRENT_BRANCH"
echo "   Then create PR on GitHub"

# Exit with error code only if blocking issues found
if [ "$BLOCKING_ISSUES" = true ]; then
    exit 1
else
    exit 0
fi
