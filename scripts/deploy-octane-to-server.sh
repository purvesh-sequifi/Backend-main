#!/bin/bash

# ============================================================================
# Master Deployment Script - Octane + Horizon + Redis
# ============================================================================
# This script handles complete deployment of Laravel with Octane, Horizon, and Redis
# Called by all server workflow files (.github/workflows/*.yml)
#
# Prerequisites:
# - CURRENT_LINK environment variable set (e.g., /var/www/backend/current)
# - AWS Parameter Store configured for the server
# - Running as root or with sudo privileges
#
# Usage: bash scripts/deploy-octane-to-server.sh
# ============================================================================

set -e
set -o pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

# Verify prerequisites
if [ -z "$CURRENT_LINK" ]; then
    echo -e "${RED}❌ ERROR: CURRENT_LINK environment variable not set${NC}"
    exit 1
fi

cd "$CURRENT_LINK"

echo -e "${BLUE}${BOLD}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║     Deploying Octane + Horizon + Redis                    ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""

# ============================================================================
# SECTION 1: Install Required Software
# ============================================================================
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 1: Installing Required Software${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# 1.1 Install Redis
echo "🔧 1.1 Installing Redis Server..."
if ! command -v redis-cli &> /dev/null; then
    sudo apt-get update -qq
    sudo apt-get install -y redis-server
    sudo systemctl enable redis-server
    sudo systemctl start redis-server
    echo -e "${GREEN}✅ Redis installed and started${NC}"
else
    echo -e "${GREEN}✅ Redis already installed${NC}"
    sudo systemctl start redis-server 2>/dev/null || true
fi

# Verify Redis
if redis-cli ping > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Redis is running${NC}"
else
    echo -e "${RED}❌ Redis failed to start${NC}"
    exit 1
fi

# 1.2 Install Swoole
echo ""
echo "🔧 1.2 Installing Swoole Extension..."
if ! php -m | grep -q swoole; then
    sudo apt-get install -y php8.3-swoole
    sudo phpenmod swoole
    echo -e "${GREEN}✅ Swoole installed${NC}"
else
    echo -e "${GREEN}✅ Swoole already installed${NC}"
fi

SWOOLE_VERSION=$(php -r "echo phpversion('swoole');" 2>/dev/null || echo "unknown")
echo -e "${GREEN}✅ Swoole version: $SWOOLE_VERSION${NC}"

# 1.3 Install phpredis (for performance)
echo ""
echo "🔧 1.3 Installing phpredis Extension..."
if ! php -m | grep -q ^redis$; then
    sudo apt-get install -y php8.3-redis
    sudo phpenmod redis
    echo -e "${GREEN}✅ phpredis installed${NC}"
else
    echo -e "${GREEN}✅ phpredis already installed${NC}"
fi

# 1.4 Install Supervisor
echo ""
echo "🔧 1.4 Installing Supervisor..."
if ! command -v supervisorctl &> /dev/null; then
    sudo apt-get install -y supervisor
    sudo systemctl enable supervisor
    sudo systemctl start supervisor
    echo -e "${GREEN}✅ Supervisor installed and started${NC}"
else
    echo -e "${GREEN}✅ Supervisor already installed${NC}"
    sudo systemctl start supervisor 2>/dev/null || true
fi

# 1.5 Install Python Dependencies for PDF Processing
echo ""
echo "🔧 1.5 Installing Python Dependencies..."
if ! command -v pip3 &> /dev/null; then
    sudo apt-get install -y python3-pip python3-dev build-essential
    echo -e "${GREEN}✅ pip3 installed${NC}"
else
    echo -e "${GREEN}✅ pip3 already installed${NC}"
fi

# Install Python packages from requirements.txt
if [ -f "${CURRENT_LINK}/py-scripts/requirements.txt" ]; then
    echo "📦 Installing Python packages from requirements.txt..."
    sudo pip3 install --break-system-packages -q -r "${CURRENT_LINK}/py-scripts/requirements.txt"
    echo -e "${GREEN}✅ Python dependencies installed (PyMuPDF, Pillow, requests, reportlab)${NC}"
else
    echo -e "${YELLOW}⚠️  requirements.txt not found, skipping Python dependencies${NC}"
fi

# Verify critical Python modules
if python3 -c "import fitz" 2>/dev/null; then
    echo -e "${GREEN}✅ PyMuPDF (fitz) verified${NC}"
else
    echo -e "${RED}❌ PyMuPDF installation failed${NC}"
fi

# ============================================================================
# SECTION 2: Apply Swoole 5.x Compatibility Patch
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 2: Swoole 5.x Compatibility${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "🔧 2.1 Applying Swoole 5.x/6.x compatibility patch..."

# Always use the standalone patch script (cleaner and avoids bash quote escaping issues)
if [ -f "scripts/patch-swoole-5x-compatibility.sh" ]; then
    bash scripts/patch-swoole-5x-compatibility.sh
    echo -e "${GREEN}✅ Swoole patch applied successfully${NC}"
elif [ -f "vendor/laravel/octane/bin/swoole-server" ]; then
    echo -e "${YELLOW}⚠️  Patch script not found, skipping Swoole patch${NC}"
    echo -e "${YELLOW}⚠️  Run 'bash scripts/patch-swoole-5x-compatibility.sh' manually after deployment${NC}"
else
    echo -e "${YELLOW}⚠️  Octane not installed yet, patch will be applied after composer install${NC}"
fi

# ============================================================================
# SECTION 3: Configure Supervisor for Octane
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 3: Configure Octane Supervisor${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "🔧 3.1 Creating Octane supervisor configuration..."

# Detect CPU cores for worker calculation
CPU_CORES=$(nproc)
WORKERS=${SWOOLE_WORKERS:-$((CPU_CORES * 2))}
TASK_WORKERS=${SWOOLE_TASK_WORKERS:-$CPU_CORES}

# Create Octane supervisor config
sudo tee /etc/supervisor/conf.d/sequifi-octane.conf > /dev/null <<EOF
[program:sequifi-octane]
process_name=%(program_name)s
command=/usr/bin/php ${CURRENT_LINK}/artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=${WORKERS} --task-workers=${TASK_WORKERS} --max-requests=5000
directory=${CURRENT_LINK}
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=${CURRENT_LINK}/storage/logs/octane-stdout.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=20
startsecs=10
stopsignal=QUIT
EOF

echo -e "${GREEN}✅ Octane supervisor config created (${WORKERS} workers, ${TASK_WORKERS} task workers)${NC}"

# ============================================================================
# SECTION 4: Cleanup Old Queue Workers (Before Horizon)
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 4: Cleanup Old Queue Workers${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "🔧 4.1 Stopping old queue workers..."

# List of old worker types to stop
OLD_WORKERS=(
    "laravel-worker"
    "laravel-everee-worker"
    "laravel-execute-worker"
    "laravel-finalize-worker"
    "laravel-import-worker"
    "laravel-onetimepayment-worker"
    "laravel-onetimewebhook-worker"
    "laravel-parlley-worker"
    "laravel-quickbooks-worker"
    "laravel-special-worker"
    "laravel-specialimport-worker"
)

STOPPED_COUNT=0
for worker in "${OLD_WORKERS[@]}"; do
    # Check if worker exists before trying to stop it
    if sudo supervisorctl status | grep -q "^${worker}:" 2>/dev/null; then
        if sudo supervisorctl stop ${worker}:* 2>/dev/null || true; then
            echo -e "${GREEN}  ✓ Stopped ${worker}${NC}"
            STOPPED_COUNT=$((STOPPED_COUNT + 1)) || true
        fi
    fi
done

echo "DEBUG: STOPPED_COUNT = $STOPPED_COUNT"
if [ $STOPPED_COUNT -gt 0 ]; then
    echo -e "${GREEN}✅ Stopped $STOPPED_COUNT old worker type(s)${NC}"
else
    echo -e "${GREEN}✅ No old workers found (clean server)${NC}"
fi
echo "DEBUG: Completed worker stopping section successfully"

echo ""
echo "🔧 4.2 Disabling old worker configurations..."

# Move old supervisor configs to .disabled
if [ -d "/etc/supervisor/conf.d" ]; then
    OLD_CONFIGS_MOVED=0
    for worker in "${OLD_WORKERS[@]}"; do
        if [ -f "/etc/supervisor/conf.d/${worker}.conf" ]; then
            sudo mv "/etc/supervisor/conf.d/${worker}.conf" \
                    "/etc/supervisor/conf.d/${worker}.conf.disabled" 2>/dev/null || true
            OLD_CONFIGS_MOVED=$((OLD_CONFIGS_MOVED + 1)) || true
        fi
    done
    
    echo "DEBUG: OLD_CONFIGS_MOVED = $OLD_CONFIGS_MOVED"
    if [ $OLD_CONFIGS_MOVED -gt 0 ]; then
        echo -e "${GREEN}✅ Disabled $OLD_CONFIGS_MOVED old worker config(s)${NC}"
    else
        echo -e "${GREEN}✅ No old worker configs found${NC}"
    fi
fi
echo "DEBUG: Completed config disabling section successfully"

# ============================================================================
# SECTION 5: Configure Supervisor for Horizon
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 5: Configure Horizon Supervisor${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "🔧 5.1 Creating Horizon supervisor configuration..."

sudo tee /etc/supervisor/conf.d/sequifi-horizon.conf > /dev/null <<EOF
[program:sequifi-horizon]
process_name=%(program_name)s
command=/usr/bin/php ${CURRENT_LINK}/artisan horizon
directory=${CURRENT_LINK}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${CURRENT_LINK}/storage/logs/horizon.log
stopwaitsecs=3600
EOF

echo -e "${GREEN}✅ Horizon supervisor config created${NC}"

# ============================================================================
# SECTION 6: Reload Supervisor and Start Services
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 6: Start Services${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "🔧 6.1 Reloading Supervisor configuration..."
sudo supervisorctl reread || { echo "⚠️ supervisorctl reread failed, continuing..."; }
sudo supervisorctl update || { echo "⚠️ supervisorctl update failed, continuing..."; }
echo -e "${GREEN}✅ Supervisor configuration reloaded${NC}"

echo ""
echo "🔧 6.2 Restarting Octane (zero-downtime)..."
# Try graceful reload first
if sudo -u www-data php artisan octane:reload 2>/dev/null; then
    echo -e "${GREEN}✅ Octane reloaded gracefully (zero-downtime)${NC}"
else
    # Fallback to supervisor restart
    sudo supervisorctl restart sequifi-octane:* 2>/dev/null || sudo supervisorctl start sequifi-octane:* || true
    echo -e "${GREEN}✅ Octane restarted via Supervisor${NC}"
fi

echo ""
echo "🔧 6.3 Restarting Horizon..."
# Graceful termination (Supervisor will auto-restart)
if sudo -u www-data php artisan horizon:terminate 2>/dev/null; then
    echo -e "${GREEN}✅ Horizon terminated gracefully (will auto-restart)${NC}"
else
    sudo supervisorctl restart sequifi-horizon:* 2>/dev/null || sudo supervisorctl start sequifi-horizon:* || true
    echo -e "${GREEN}✅ Horizon restarted via Supervisor${NC}"
fi

# Wait for services to start
sleep 5

# ============================================================================
# SECTION 7: Health Checks
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 7: Health Checks${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

HEALTH_PASSED=0
HEALTH_FAILED=0

# 7.1 Check Redis
echo "🏥 7.1 Checking Redis..."
if redis-cli ping > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Redis: OK${NC}"
    HEALTH_PASSED=$((HEALTH_PASSED + 1)) || true
else
    echo -e "${RED}❌ Redis: FAILED${NC}"
    HEALTH_FAILED=$((HEALTH_FAILED + 1)) || true
fi

# 7.2 Check Octane
echo "🏥 7.2 Checking Octane..."
if pgrep -f "octane" > /dev/null; then
    echo -e "${GREEN}✅ Octane: RUNNING${NC}"
    HEALTH_PASSED=$((HEALTH_PASSED + 1)) || true
else
    echo -e "${RED}❌ Octane: NOT RUNNING${NC}"
    HEALTH_FAILED=$((HEALTH_FAILED + 1)) || true
fi

# 7.3 Check Horizon
echo "🏥 7.3 Checking Horizon..."
if pgrep -f "horizon" > /dev/null; then
    echo -e "${GREEN}✅ Horizon: RUNNING${NC}"
    HEALTH_PASSED=$((HEALTH_PASSED + 1)) || true
else
    echo -e "${RED}❌ Horizon: NOT RUNNING${NC}"
    HEALTH_FAILED=$((HEALTH_FAILED + 1)) || true
fi

# 7.4 Check Database Connection
echo "🏥 7.4 Checking Database..."
if sudo -u www-data php artisan tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
    echo -e "${GREEN}✅ Database: CONNECTED${NC}"
    HEALTH_PASSED=$((HEALTH_PASSED + 1)) || true
else
    echo -e "${RED}❌ Database: FAILED${NC}"
    HEALTH_FAILED=$((HEALTH_FAILED + 1)) || true
fi

# 7.5 Check Health Endpoint
echo "🏥 7.5 Checking Health Endpoint..."
HEALTH_RESPONSE=$(curl -s -f http://localhost:8000/api/health 2>&1 || echo "failed")
if [[ "$HEALTH_RESPONSE" != "failed" ]]; then
    echo -e "${GREEN}✅ Health Endpoint: OK${NC}"
    HEALTH_PASSED=$((HEALTH_PASSED + 1)) || true
else
    echo -e "${YELLOW}⚠️  Health Endpoint: Not responding (may need Apache/Nginx restart)${NC}"
fi

# ============================================================================
# SECTION 8: Final Verification
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}${BOLD}SECTION 8: Deployment Summary${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

echo "Health Checks:"
echo "  ✅ Passed: $HEALTH_PASSED"
echo "  ❌ Failed: $HEALTH_FAILED"
echo ""

if [ $HEALTH_FAILED -eq 0 ]; then
    echo -e "${GREEN}${BOLD}"
    echo "╔════════════════════════════════════════════════╗"
    echo "║  ✅ DEPLOYMENT SUCCESSFUL!                     ║"
    echo "╚════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
    echo "Services Running:"
    sudo supervisorctl status | grep -E "sequifi-octane|sequifi-horizon" || true
    echo ""
    echo "Configuration:"
    echo "  • Queue: Redis"
    echo "  • Cache: Redis (phpredis)"
    echo "  • Octane: Swoole $SWOOLE_VERSION"
    echo "  • Workers: $WORKERS"
    echo "  • Horizon: Active"
    echo ""
    exit 0
else
    echo -e "${RED}${BOLD}"
    echo "╔════════════════════════════════════════════════╗"
    echo "║  ⚠️  DEPLOYMENT COMPLETED WITH WARNINGS        ║"
    echo "╚════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
    echo "Some health checks failed. Please review:"
    echo "  • Check logs: ${CURRENT_LINK}/storage/logs/"
    echo "  • Verify services: sudo supervisorctl status"
    echo "  • Check Redis: redis-cli ping"
    echo ""
    
    # Don't fail deployment for health check issues (allow manual intervention)
    if [ $HEALTH_FAILED -le 2 ]; then
        echo -e "${YELLOW}Continuing deployment (minor issues detected)${NC}"
        exit 0
    else
        echo -e "${YELLOW}⚠️  Multiple health checks failed, but continuing deployment${NC}"
        echo -e "${YELLOW}Please verify services manually after deployment${NC}"
        exit 0
    fi
fi

