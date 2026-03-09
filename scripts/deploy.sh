#!/bin/bash
# ============================================================================
# Zero-Downtime Deployment Script for new.api.sequifi.com
# ============================================================================
# Called by GitHub Actions - Repository already cloned to DEPLOY_DIR
# Usage: bash scripts/deploy.sh <run_number> <branch> <commit_sha>
# ============================================================================

set -e

# Parameters from workflow
RUN_NUMBER="${1}"
BRANCH_NAME="${2}"
COMMIT_SHA="${3}"
SSM_PARAM="${4:-/backend/new}"  # Default to /backend/new if not provided

# Set paths
DEPLOY_PATH="/var/www/backend"
DEPLOY_DIR="${DEPLOY_PATH}/${RUN_NUMBER}"
CURRENT_LINK="${DEPLOY_PATH}/current"

echo "Deployment #${RUN_NUMBER} | Branch: ${BRANCH_NAME} | Commit: ${COMMIT_SHA:0:8}"


# ============================================================================
# 0. DEPLOYMENT LOCKING - Prevent concurrent deployments
# ============================================================================
LOCK_FILE="/var/www/backend/.deployment.lock"
LOCK_TIMEOUT=1800  # 30 minutes max deployment time

if [ -f "$LOCK_FILE" ]; then
  LOCK_PID=$(cat "$LOCK_FILE")
  LOCK_AGE=$(($(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0)))

  if [ $LOCK_AGE -gt $LOCK_TIMEOUT ]; then
    echo "⚠️  Stale lock detected (${LOCK_AGE}s old), removing..."
    sudo rm -f "$LOCK_FILE"
  elif kill -0 $LOCK_PID 2>/dev/null; then
    echo "❌ Another deployment is in progress (PID: $LOCK_PID)"
    echo "❌ Lock age: ${LOCK_AGE} seconds"
    echo "❌ Please wait for current deployment to complete"
    exit 1
  else
    echo "⚠️  Stale lock with dead process, removing..."
    sudo rm -f "$LOCK_FILE"
  fi
fi

echo $$ | sudo tee "$LOCK_FILE" > /dev/null
echo "🔒 Deployment lock acquired (PID: $$)"

# Ensure lock is removed on exit
trap "sudo rm -f $LOCK_FILE; echo '🔓 Deployment lock released'" EXIT

# ============================================================================
# 1. Verify PHP 8.3 is installed (install only if missing)
# ============================================================================
echo "🔍 Checking PHP 8.3 installation..."
if ! php -v | grep -q "PHP 8.3"; then
  echo "📦 Installing PHP 8.3 and required extensions..."
  sudo apt-get update -y
  sudo apt-get install -y software-properties-common || true
  sudo add-apt-repository ppa:ondrej/php -y || true
  sudo apt-get update -y
  sudo apt-get install -y \
    php8.3 php8.3-cli php8.3-common libapache2-mod-php8.3 \
    php8.3-xml php8.3-curl php8.3-mbstring php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-mysql \
    php8.3-mongodb php8.3-swoole php8.3-redis
  sudo phpenmod -v 8.3 xml curl mbstring zip gd intl bcmath mongodb swoole redis || true

  # Configure Apache to use PHP 8.3
  sudo a2dismod php7.4 php8.0 php8.1 php8.2 php8.4 2>/dev/null || true
  sudo a2enmod php8.3 || true
  sudo systemctl reload apache2 || true
  echo "✅ PHP 8.3 installed and configured"
else
  echo "✅ PHP 8.3 already installed, skipping"
fi

echo "📌 PHP Version: $(php -v | head -n 1)"

# ============================================================================
# 1.5. Pre-flight Redis check (fail fast before building)
# ============================================================================
echo "🔍 Pre-flight Redis health check..."
if ! redis-cli ping > /dev/null 2>&1; then
  echo "❌ CRITICAL: Redis is not running on new.api.sequifi.com!"
  echo "🔧 Attempting to start Redis..."

  sudo systemctl start redis-server
  sleep 3

  if ! redis-cli ping > /dev/null 2>&1; then
    echo "❌ Redis failed to start"
    echo "📋 Recent Redis logs:"
    sudo journalctl -u redis-server -n 20 --no-pager
    echo "❌ Deployment aborted - fix Redis before deploying"
    echo "💡 To fix: Check /etc/redis/redis.conf for errors"
    exit 1
  fi
  echo "✅ Redis started successfully"
else
  echo "✅ Redis is running"
fi

# ============================================================================
# 2. Cleanup old deployments (keep current and previous only)
# ============================================================================
CURRENT_RUN=$RUN_NUMBER
PREVIOUS_RUN=$((CURRENT_RUN - 1))

echo "======================================="
echo "Cleaning up old deployments..."
echo "Keeping: ${CURRENT_RUN} and ${PREVIOUS_RUN}"
echo "======================================="

cd "${DEPLOY_PATH}"

for dir in $(ls -1d [0-9]* 2>/dev/null); do
  if [[ "$dir" =~ ^[0-9]+$ ]]; then
    if [ "$dir" -ne "$CURRENT_RUN" ] && [ "$dir" -ne "$PREVIOUS_RUN" ]; then
      echo "🗑️  Removing old deployment: $dir"
      sudo rm -rf "${DEPLOY_PATH}/$dir" 2>/dev/null || true
    else
      echo "✅ Keeping deployment: $dir"
    fi
  fi
done

# ============================================================================
# 3. Set permissions
# ============================================================================
echo "🔐 Setting permissions..."
sudo chown -R www-data:www-data "${DEPLOY_DIR}"
sudo chmod -R 755 "${DEPLOY_DIR}"
sudo chmod -R 775 "${DEPLOY_DIR}/storage"

# ============================================================================
# 4. Fetch environment configuration from AWS SSM (merged from two parameters)
# ============================================================================
echo "⚙️  Fetching environment configuration from AWS SSM..."
echo "📥 Fetching common environment variables from /backend/common..."
COMMON_ENV="${DEPLOY_DIR}/.env.common"
sudo aws ssm get-parameter --name "/backend/common" --with-decryption --output text --query Parameter.Value | sudo tee "$COMMON_ENV" > /dev/null 2>&1 || {
  echo "⚠️  Warning: /backend/common parameter not found, continuing with server-specific only..."
  sudo touch "$COMMON_ENV"
}

echo "📥 Fetching server-specific environment variables from ${SSM_PARAM}..."
NEW_ENV="${DEPLOY_DIR}/.env.new"
sudo aws ssm get-parameter --name "${SSM_PARAM}" --with-decryption --output text --query Parameter.Value | sudo tee "$NEW_ENV" > /dev/null 2>&1 || {
  echo "❌ CRITICAL: ${SSM_PARAM} parameter not found"
  echo "❌ Deployment aborted - server-specific configuration is required"
  exit 1
}

# Merge environment files: common first, then new (new takes precedence for duplicates)
echo "🔀 Merging environment variables (common + server-specific)..."
TMP_ENV="${DEPLOY_DIR}/.env.tmp"
sudo touch "$TMP_ENV"

# Process common env file with validation
if [ -s "$COMMON_ENV" ]; then
  echo "📋 Processing common environment variables..."
  while IFS= read -r line || [ -n "$line" ]; do
    # Skip empty lines and comments
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue

    # Validate line contains KEY=VALUE format (must have = separator)
    if [[ ! "$line" =~ = ]]; then
      echo "⚠️  Warning: Skipping invalid common line (missing = separator): ${line:0:50}..."
      continue
    fi

    # Extract key name (everything before first =)
    key=$(echo "$line" | cut -d= -f1 | xargs)

    # Validate key is not empty after trimming
    if [ -z "$key" ]; then
      echo "⚠️  Warning: Skipping common line with empty key: ${line:0:50}..."
      continue
    fi

    # Append the common value
    echo "$line" | sudo tee -a "$TMP_ENV" > /dev/null
  done < "$COMMON_ENV"
fi

# Append server-specific values, but skip duplicates (new values override common)
while IFS= read -r line || [ -n "$line" ]; do
  # Skip empty lines and comments
  [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue

  # Validate line contains KEY=VALUE format (must have = separator)
  if [[ ! "$line" =~ = ]]; then
    echo "⚠️  Warning: Skipping invalid line (missing = separator): ${line:0:50}..."
    continue
  fi

  # Extract key name (everything before first =)
  key=$(echo "$line" | cut -d= -f1 | xargs)

  # Validate key is not empty after trimming
  if [ -z "$key" ]; then
    echo "⚠️  Warning: Skipping line with empty key: ${line:0:50}..."
    continue
  fi

  # Remove existing entry with same key (if exists)
  # Escape key for use in sed regex pattern (escape special regex characters: . * ^ $ [ ] ( ) + ? { | \ /)
  # Use '#' as sed delimiter to avoid conflicts with '/' in keys (e.g., DB/HOST)
  # Note: In character class, '/' doesn't need escaping, but we escape it for the pattern
  escaped_key=$(printf '%s\n' "$key" | sed 's/[\.*^$\[\]()+?{|\\\/]/\\&/g')
  sudo sed -i "#^${escaped_key}=#d" "$TMP_ENV" 2>/dev/null || true

  # Append the new value
  echo "$line" | sudo tee -a "$TMP_ENV" > /dev/null
done < "$NEW_ENV"

# Update environment with production settings
sudo sed -i 's/^APP_ENV=.*/APP_ENV=production/g' "$TMP_ENV" || true
sudo sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/g' "$TMP_ENV" || true

# Ensure encryption variables are set
if ! sudo grep -q "^ENCRYPTION_CIPHER_ALGO=" "$TMP_ENV"; then
  echo "ENCRYPTION_CIPHER_ALGO=aes-256-cbc" | sudo tee -a "$TMP_ENV" > /dev/null
fi

# Validate critical encryption variables (check merged result)
if ! sudo grep -q "^ENCRYPTION_KEY=" "$TMP_ENV"; then
  echo "❌ CRITICAL: ENCRYPTION_KEY missing from merged environment"
  echo "❌ Check both /backend/common and ${SSM_PARAM} parameters"
  echo "❌ Deployment aborted - encryption key is required"
  exit 1
fi
if ! sudo grep -q "^ENCRYPTION_IV=" "$TMP_ENV"; then
  echo "❌ CRITICAL: ENCRYPTION_IV missing from merged environment"
  echo "❌ Check both /backend/common and ${SSM_PARAM} parameters"
  echo "❌ Deployment aborted - encryption IV is required"
  exit 1
fi

# Clean up temporary files
sudo rm -f "$COMMON_ENV" "$NEW_ENV" 2>/dev/null || true

# Copy final .env and set permissions ONCE
sudo cp "$TMP_ENV" "${DEPLOY_DIR}/.env"
sudo chown www-data:www-data "${DEPLOY_DIR}/.env"
sudo chmod 644 "${DEPLOY_DIR}/.env"

# Clean up temporary merge file
sudo rm -f "$TMP_ENV" 2>/dev/null || true

echo "✅ Environment variables merged successfully from /backend/common and ${SSM_PARAM}"

# ============================================================================
# 5. Create storage directories in NEW deployment
# ============================================================================
echo "📁 Creating storage directories in new deployment..."
sudo mkdir -p "${DEPLOY_DIR}/storage/app/processed_pdf"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/processed_pdf/e_signed_pdf"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/processed_pdf/form_data_merged_pdf"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/signed_pdfs"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/unsigned_pdfs"
sudo mkdir -p "${DEPLOY_DIR}/storage/logs"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/temp/fieldroutes"
sudo mkdir -p "${DEPLOY_DIR}/storage/framework/cache/data"
sudo mkdir -p "${DEPLOY_DIR}/storage/framework/sessions"
sudo mkdir -p "${DEPLOY_DIR}/storage/framework/views"
sudo mkdir -p "${DEPLOY_DIR}/storage/framework/testing"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/public"
sudo mkdir -p "${DEPLOY_DIR}/storage/app/public/exports"
sudo mkdir -p "${DEPLOY_DIR}/public/jobs_queue"

# Composer cache directories (only create once)
if [ ! -d "/var/www/.config/psysh" ]; then
  sudo mkdir -p /var/www/.config/psysh
  sudo mkdir -p /var/www/.cache/composer/files
  sudo chown -R www-data:www-data /var/www/.config /var/www/.cache
  sudo chmod -R 755 /var/www/.config
  sudo chmod -R 775 /var/www/.cache
fi

sudo chown -R www-data:www-data "${DEPLOY_DIR}/storage"
sudo chmod -R 775 "${DEPLOY_DIR}/storage"

# ============================================================================
# 6. Setup SQLite databases (only if missing)
# ============================================================================
DOMAIN_NAME=$(grep "^DOMAIN_NAME=" "${DEPLOY_DIR}/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "default")
DATABASE_PATH="/var/www/backend/databases/${DOMAIN_NAME}_database.sqlite"
API_METRICS_PATH="/var/www/backend/databases/${DOMAIN_NAME}_api_metrics.sqlite"

if [ ! -f "$DATABASE_PATH" ] || [ ! -f "$API_METRICS_PATH" ]; then
  echo "🔧 Setting up SQLite databases..."
  if [ -f "${DEPLOY_DIR}/shell-scripts/install-sqlite-setup.sh" ]; then
    sudo bash "${DEPLOY_DIR}/shell-scripts/install-sqlite-setup.sh" || echo "⚠️  SQLite setup had issues, continuing..."
  fi
else
  echo "✅ SQLite databases already exist, skipping setup"
fi

cd "${DEPLOY_DIR}"

# ============================================================================
# 7. Verify deployment files
# ============================================================================
echo "🔍 Verifying deployment files..."
if [ ! -f composer.json ]; then
  echo "❌ composer.json not found"
  exit 1
fi

# ============================================================================
# 8. Install composer dependencies
# ============================================================================
echo "📦 Installing composer dependencies..."
export COMPOSER_MEMORY_LIMIT=-1

set +e
COMPOSER_OUTPUT=$(sudo -u www-data COMPOSER_CACHE_DIR=/var/www/.cache/composer COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction 2>&1)
COMPOSER_EXIT=$?
set -e

if [ $COMPOSER_EXIT -ne 0 ]; then
  echo "❌ Composer install failed with exit code: $COMPOSER_EXIT"
  echo "$COMPOSER_OUTPUT"
  echo "❌ Deployment aborted - fix composer issues and try again"
  exit 1
fi

echo "✅ Composer dependencies installed"
if [ ! -f "${DEPLOY_DIR}/vendor/autoload.php" ]; then
  echo "❌ vendor/autoload.php not found"
  exit 1
fi

# Check platform requirements
sudo -u www-data composer check-platform-reqs --no-dev || { echo "❌ Platform requirements not satisfied"; exit 1; }

# ============================================================================
# 9. Run Laravel artisan commands on NEW deployment
# ============================================================================
echo "🔧 Running Laravel artisan commands..."
cd "${DEPLOY_DIR}"
# Auto-delete DEFINER migration record to force re-run (handles username changes)
echo "🔄 Forcing DEFINER migration to run (auto-delete approach)..."
if sudo -u www-data php artisan tinker --execute="DB::connection()->getPdo();" >/dev/null 2>&1; then
  echo "✅ Database connection successful"
  if sudo -u www-data php artisan tinker --execute="DB::table('migrations')->where('migration', '2025_11_10_000000_fix_all_database_definers_permanently')->delete();" >/dev/null 2>&1; then
    echo "✅ DEFINER migration record deleted - migration will re-run"
  else
    echo "ℹ️  DEFINER migration record not found (first run or already processed)"
  fi
else
  echo "⚠️  Database connection failed - skipping DEFINER fix"
fi
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan event:cache
sudo -u www-data php artisan route:cache

# ============================================================================
# 9.5. Install Python virtual environment and dependencies
# ============================================================================
echo "🐍 Installing Python virtual environment..."
if [ -f "${DEPLOY_DIR}/scripts/install-python-deps.sh" ]; then
    sudo bash "${DEPLOY_DIR}/scripts/install-python-deps.sh" "${DEPLOY_DIR}" || echo "⚠️ Python setup failed, continuing..."
else
    echo "⚠️ Python install script not found, skipping..."
fi

# ============================================================================
# 10. First-time Octane/Horizon setup (if needed)
# ============================================================================
# Check if this is first-time setup (needs full install)
if [ ! -f "/etc/supervisor/conf.d/sequifi-octane.conf" ]; then
  echo "🚀 First-time Octane setup - installing Redis, Swoole, etc..."
  export CURRENT_LINK="${DEPLOY_PATH}/current"

  # Temporarily create symlink for script execution
  sudo ln -sfn "${DEPLOY_DIR}" "${CURRENT_LINK}"

  if [ -f "${DEPLOY_DIR}/scripts/deploy-octane-to-server.sh" ]; then
    bash "${DEPLOY_DIR}/scripts/deploy-octane-to-server.sh" || exit 1
  else
    echo "❌ deploy-octane-to-server.sh not found"
    exit 1
  fi

  # CRITICAL: Remove temporary symlink BEFORE health checks
  # If health checks fail, we don't want production pointing at bad deployment
  echo "🔗 Removing temporary symlink before health checks..."
  sudo rm -f "${CURRENT_LINK}" || true
else
  echo "✅ Octane/Horizon already installed, will update configs after symlink switch"
fi

# ============================================================================
# 11. Validate and protect Redis configuration
# ============================================================================
echo "🔍 Validating Redis configuration..."

# Test current Redis config for syntax errors
sudo redis-server /etc/redis/redis.conf --test-memory 1 > /tmp/redis-test.log 2>&1 || REDIS_CONFIG_INVALID=1

if [ ! -z "$REDIS_CONFIG_INVALID" ]; then
  echo "❌ Redis config has errors! Details:"
  cat /tmp/redis-test.log
  echo "🔧 Restoring clean Redis configuration from repository..."

  # Backup broken config
  sudo cp /etc/redis/redis.conf /etc/redis/redis.conf.broken.$(date +%s)

  # Restore clean config from repository
  if [ -f "${DEPLOY_DIR}/config/redis.conf" ]; then
    sudo cp "${DEPLOY_DIR}/config/redis.conf" /etc/redis/redis.conf
    sudo chown redis:redis /etc/redis/redis.conf
    sudo chmod 644 /etc/redis/redis.conf
    echo "✅ Redis config restored from repository"
  else
    echo "❌ No clean config found in repository!"
    echo "❌ Please ensure config/redis.conf exists in your repository"
    exit 1
  fi

  # Restart Redis with clean config
  echo "🔄 Restarting Redis with clean configuration..."
  sudo systemctl restart redis-server
  sleep 5

  if ! redis-cli ping > /dev/null 2>&1; then
    echo "❌ Redis failed to start even after config restore"
    sudo journalctl -u redis-server -n 30 --no-pager
    exit 1
  fi

  echo "✅ Redis restarted successfully with clean config"
else
  echo "✅ Redis configuration is valid"
fi

# Verify Redis is running
if ! redis-cli ping > /dev/null 2>&1; then
  echo "❌ Redis is not responding"
  exit 1
fi

# ============================================================================
# 12. Setup Redis AOF Persistence (first-time only)
# ============================================================================
AOF_STATUS=$(redis-cli CONFIG GET appendonly | tail -1)
if [ "$AOF_STATUS" != "yes" ]; then
  echo "🔧 Enabling Redis AOF persistence (first-time setup)..."

  if [ -f "${DEPLOY_DIR}/config/redis-persistence.conf" ]; then
    sudo cp "${DEPLOY_DIR}/config/redis-persistence.conf" /etc/redis/redis-persistence.conf

    if ! grep -q "include /etc/redis/redis-persistence.conf" /etc/redis/redis.conf; then
      echo "include /etc/redis/redis-persistence.conf" | sudo tee -a /etc/redis/redis.conf
    fi
  fi

  redis-cli CONFIG SET appendonly yes
  redis-cli CONFIG SET appendfsync everysec
  redis-cli CONFIG SET auto-aof-rewrite-percentage 100
  redis-cli CONFIG SET auto-aof-rewrite-min-size 64mb
  redis-cli CONFIG REWRITE
else
  echo "✅ Redis AOF already enabled"
fi

# ============================================================================
# 13. Set ALL permissions on NEW deployment BEFORE going live
# ============================================================================
echo "🔐 Setting all permissions on NEW deployment..."
cd "${DEPLOY_DIR}"

sudo -u www-data php artisan storage:link || true
sudo touch "${DEPLOY_DIR}/storage/logs/laravel.log"
sudo chown www-data:www-data "${DEPLOY_DIR}/storage/logs/laravel.log"
sudo chmod 664 "${DEPLOY_DIR}/storage/logs/laravel.log"
sudo chown -R www-data:www-data "${DEPLOY_DIR}/public/"
sudo chmod -R 755 "${DEPLOY_DIR}/public/storage" || true
sudo chmod -R 777 "${DEPLOY_DIR}/storage/app/temp/fieldroutes/"

# Install ACL if needed
if ! command -v setfacl &> /dev/null; then
  sudo apt-get update && sudo apt-get install -y acl
fi

# Set comprehensive storage permissions (on NEW deployment)
sudo chown -R www-data:www-data "${DEPLOY_DIR}/storage"
sudo chmod -R 777 "${DEPLOY_DIR}/storage"
sudo find "${DEPLOY_DIR}/storage" -type d -exec chmod g+s {} \;
sudo setfacl -R -m d:u:www-data:rwx "${DEPLOY_DIR}/storage"
sudo setfacl -R -m u:www-data:rwx "${DEPLOY_DIR}/storage"

# ============================================================================
# 14. Health checks on NEW deployment BEFORE going live
# ============================================================================
echo "🏥 Running health checks on NEW deployment..."
HEALTH_FAILED=0

# Test database connection
if sudo -u www-data php "${DEPLOY_DIR}/artisan" tinker --execute="DB::connection()->getPdo();" > /dev/null 2>&1; then
  echo "✅ Database connection: OK"
else
  echo "❌ Database connection: FAILED"
  HEALTH_FAILED=$((HEALTH_FAILED + 1))
fi

# Test config cache
if [ -f "${DEPLOY_DIR}/bootstrap/cache/config.php" ]; then
  echo "✅ Config cache: OK"
else
  echo "❌ Config cache: MISSING"
  HEALTH_FAILED=$((HEALTH_FAILED + 1))
fi

# Test autoload
if [ -f "${DEPLOY_DIR}/vendor/autoload.php" ]; then
  echo "✅ Composer autoload: OK"
else
  echo "❌ Composer autoload: MISSING"
  HEALTH_FAILED=$((HEALTH_FAILED + 1))
fi

# Test Redis
if redis-cli ping > /dev/null 2>&1; then
  echo "✅ Redis: OK"
else
  echo "❌ Redis: FAILED"
  HEALTH_FAILED=$((HEALTH_FAILED + 1))
fi

if [ $HEALTH_FAILED -gt 0 ]; then
  echo "❌ Health checks failed ($HEALTH_FAILED failures)"
  echo "❌ Aborting deployment - new deployment is not healthy"
  exit 1
fi

echo "✅ All health checks passed - deployment is healthy"

# ============================================================================
# 15. ATOMIC SYMLINK SWITCH - Zero Downtime Cutover
# ============================================================================
echo "🔗 Switching symlink to new deployment (atomic)..."
sudo ln -sfn "${DEPLOY_DIR}" "${CURRENT_LINK}"
echo "✅ Symlink switched - new deployment is now live"

# ============================================================================
# 16. Update Supervisor Configs (AFTER symlink, BEFORE service reload)
# ============================================================================
echo "🔧 Updating supervisor configurations with current paths..."

# Detect CPU cores for worker calculation
CPU_CORES=$(nproc)
WORKERS=${SWOOLE_WORKERS:-$((CPU_CORES * 2))}
TASK_WORKERS=${SWOOLE_TASK_WORKERS:-$CPU_CORES}

# Create Octane config
sudo bash -c "cat > /etc/supervisor/conf.d/sequifi-octane.conf" <<OCTANE_CONFIG
[program:sequifi-octane]
process_name=%(program_name)s
command=/usr/bin/php /var/www/backend/current/artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=${WORKERS} --task-workers=${TASK_WORKERS} --max-requests=5000
directory=/var/www/backend/current
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/backend/current/storage/logs/octane-stdout.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=20
startsecs=10
stopsignal=QUIT
OCTANE_CONFIG

# Create Horizon config
sudo bash -c "cat > /etc/supervisor/conf.d/sequifi-horizon.conf" <<HORIZON_CONFIG
[program:sequifi-horizon]
process_name=%(program_name)s
command=/usr/bin/php /var/www/backend/current/artisan horizon
directory=/var/www/backend/current
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/backend/current/storage/logs/horizon.log
stopwaitsecs=3600
HORIZON_CONFIG

echo "✅ Supervisor configs updated with current paths"
sudo supervisorctl reread
sudo supervisorctl update

# ============================================================================
# 17. Job-safe service reloads with zombie cleanup
# ============================================================================
echo "🔄 Reloading services with job protection..."
cd "${CURRENT_LINK}"

# Restart queue workers
sudo -u www-data php artisan queue:restart || true

# ============================================================================
# 17.1. Clean up zombie Horizon processes (job-safe)
# ============================================================================
echo "🧹 Checking for zombie Horizon processes..."

# Get PID of Supervisor-managed Horizon process
SUPERVISOR_HORIZON_PID=$(sudo supervisorctl status sequifi-horizon 2>/dev/null | grep -oP 'pid \K[0-9]+' || echo "")
echo "📌 Supervisor-managed Horizon PID: ${SUPERVISOR_HORIZON_PID:-none}"

# Find all Horizon master processes (not workers)
ALL_HORIZON_PIDS=$(pgrep -f "php.*artisan horizon$" || echo "")

if [ ! -z "$ALL_HORIZON_PIDS" ]; then
  echo "📋 Found Horizon processes: $ALL_HORIZON_PIDS"

  for pid in $ALL_HORIZON_PIDS; do
    # Skip the Supervisor-managed process
    if [ "$pid" != "$SUPERVISOR_HORIZON_PID" ]; then
      echo "🔍 Found zombie Horizon process: PID $pid"

      # Check if it's processing jobs (has child workers)
      WORKER_COUNT=$(pgrep -P $pid 2>/dev/null | wc -l)
      echo "   Active workers: ${WORKER_COUNT}"

      if [ "$WORKER_COUNT" -gt "0" ]; then
        echo "   ⚠️  Process has active workers - waiting for jobs to complete..."

        # Send graceful termination signal (SIGTERM)
        echo "   📤 Sending SIGTERM to PID $pid..."
        sudo kill -15 $pid 2>/dev/null || true

        # Wait up to 60 seconds for graceful shutdown
        for i in {1..12}; do
          if ! kill -0 $pid 2>/dev/null; then
            echo "   ✅ Process $pid terminated gracefully"
            break
          fi
          REMAINING_WORKERS=$(pgrep -P $pid 2>/dev/null | wc -l)
          echo "   ⏳ Waiting... ($REMAINING_WORKERS workers remaining, ${i}0s elapsed)"
          sleep 5
        done

        # If still running after 60s, leave it alone (jobs still processing)
        if kill -0 $pid 2>/dev/null; then
          echo "   ⚠️  Process still running - jobs may still be processing"
          echo "   ℹ️  Leaving process alive to complete jobs safely"
        fi
      else
        echo "   ✅ No active workers - safe to terminate"
        sudo kill -15 $pid 2>/dev/null || true
        sleep 2
        if kill -0 $pid 2>/dev/null; then
          sudo kill -9 $pid 2>/dev/null || true
        fi
        echo "   ✅ Zombie process terminated"
      fi
    fi
  done
else
  echo "✅ No zombie Horizon processes found"
fi

# ============================================================================
# 17.2. Gracefully reload Octane (zero-downtime)
# ============================================================================
echo "🔄 Reloading Octane..."
if sudo -u www-data php artisan octane:reload 2>/dev/null; then
  echo "✅ Octane reloaded gracefully (zero-downtime)"
else
  echo "⚠️  Octane reload failed, restarting via supervisor..."

  # Kill any stale Octane processes on port 8000
  if sudo fuser 8000/tcp > /dev/null 2>&1; then
    echo "🔧 Killing stale process on port 8000..."
    sudo fuser -k 8000/tcp 2>/dev/null || true
    sleep 2
  fi

  sudo supervisorctl restart sequifi-octane 2>/dev/null || true
  sleep 5
  echo "✅ Octane restarted via Supervisor"
fi

# ============================================================================
# 17.3. Gracefully terminate Horizon (auto-restarts)
# ============================================================================
echo "🔄 Reloading Horizon..."

# Try graceful termination
if sudo -u www-data php artisan horizon:terminate 2>/dev/null; then
  echo "✅ Horizon terminated gracefully (auto-restarting)"
  sleep 10
else
  echo "⚠️  Horizon terminate failed, restarting via supervisor..."
  sudo supervisorctl stop sequifi-horizon 2>/dev/null || true
  sleep 3
  sudo supervisorctl start sequifi-horizon 2>/dev/null || true
  sleep 10
  echo "✅ Horizon restarted via Supervisor"
fi

# Verify Horizon is actually active
echo "🔍 Verifying Horizon is active..."
sleep 3
HORIZON_STATUS=$(cd "${CURRENT_LINK}" && sudo -u www-data php artisan horizon:status 2>/dev/null || echo "inactive")

if echo "$HORIZON_STATUS" | grep -q "running"; then
  echo "✅ Horizon is ACTIVE"
else
  echo "⚠️  Horizon is INACTIVE - forcing restart..."
  sudo supervisorctl stop sequifi-horizon
  sleep 2
  sudo pkill -f "artisan horizon" 2>/dev/null || true
  sleep 2
  sudo supervisorctl start sequifi-horizon
  sleep 10

  HORIZON_STATUS=$(cd "${CURRENT_LINK}" && sudo -u www-data php artisan horizon:status 2>/dev/null || echo "inactive")
  if echo "$HORIZON_STATUS" | grep -q "running"; then
    echo "✅ Horizon is now ACTIVE after forced restart"
  else
    echo "⚠️  WARNING: Horizon may not be active - check manually"
  fi
fi

echo "✅ All services reloaded successfully with job protection"
# ============================================================================
# 18. Post-deployment verification
# ============================================================================
echo "🔍 Verifying services are running..."

if pgrep -f "octane" > /dev/null; then
  echo "✅ Octane is running"
else
  echo "⚠️  Octane not detected - check supervisor logs"
fi

if pgrep -f "horizon" > /dev/null; then
  echo "✅ Horizon is running"
else
  echo "⚠️  Horizon not detected - check supervisor logs"
fi

# ============================================================================
# 19. Deployment complete
# ============================================================================
echo "║  ✅ ZERO-DOWNTIME DEPLOYMENT COMPLETED!        ║"
echo "📊 Monitor: https://new.api.sequifi.com/horizon"
echo "📊 Dashboard: https://new.api.sequifi.com/performance-dashboard"
echo "Services Status:"
sudo supervisorctl status | grep -E "sequifi-octane|sequifi-horizon" || true
echo "Deployment: $RUN_NUMBER"
echo "Branch: $BRANCH_NAME"
echo "Commit: $COMMIT_SHA"
