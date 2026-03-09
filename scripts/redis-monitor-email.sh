#!/bin/bash
# ============================================================================
# Redis Monitoring Script with Email Alerts
# Domain: new.api.sequifi.com
# Alert Email: gorakh@sequifi.com
# ============================================================================

DOMAIN="new.api.sequifi.com"
ALERT_EMAIL="gorakh@sequifi.com"
SERVICE_NAME="Redis Cache Server"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Check if Redis is running
if ! redis-cli ping > /dev/null 2>&1; then
    # Redis is DOWN - Send alert
    SUBJECT="🚨 CRITICAL: Redis Down on ${DOMAIN}"
    
    BODY="ALERT: Redis Cache Server Failure
    
Domain: ${DOMAIN}
Service: ${SERVICE_NAME}
Status: DOWN
Time: ${TIMESTAMP}
Severity: CRITICAL

Details:
- Redis server is not responding to PING commands
- This will cause deployment failures
- Application performance is degraded
- Cache and session operations are failing

Action Required:
1. SSH to server: ssh ubuntu@184.72.14.95
2. Check Redis status: sudo systemctl status redis-server
3. Check Redis logs: sudo journalctl -u redis-server -n 50
4. Restart if needed: sudo systemctl restart redis-server

If Redis won't start, check configuration:
sudo redis-server /etc/redis/redis.conf --test-memory 1

Automated monitoring will continue to check every 5 minutes.

---
Automated alert from ${DOMAIN}
"

    echo "$BODY" | mail -s "$SUBJECT" "$ALERT_EMAIL"
    
    # Log to syslog
    logger -t redis-monitor "CRITICAL: Redis is DOWN on ${DOMAIN}"
    
    # Try to auto-restart (optional - remove if you don't want auto-recovery)
    echo "Attempting auto-restart of Redis..."
    sudo systemctl restart redis-server
    sleep 5
    
    if redis-cli ping > /dev/null 2>&1; then
        RECOVERY_SUBJECT="✅ RECOVERED: Redis Restarted on ${DOMAIN}"
        RECOVERY_BODY="Redis has been automatically restarted and is now responding.

Domain: ${DOMAIN}
Status: RUNNING
Recovery Time: $(date '+%Y-%m-%d %H:%M:%S')

Please verify all services are functioning properly.
"
        echo "$RECOVERY_BODY" | mail -s "$RECOVERY_SUBJECT" "$ALERT_EMAIL"
        logger -t redis-monitor "INFO: Redis auto-recovered on ${DOMAIN}"
    fi
else
    # Redis is UP - Check for high memory usage
    REDIS_MEMORY=$(redis-cli INFO memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
    REDIS_CLIENTS=$(redis-cli INFO clients | grep connected_clients | cut -d: -f2 | tr -d '\r')
    
    # Optional: Alert if memory usage is very high (over 6GB on 8GB server)
    MEMORY_MB=$(redis-cli INFO memory | grep used_memory: | cut -d: -f2 | tr -d '\r' | awk '{print int($1/1024/1024)}')
    
    if [ "$MEMORY_MB" -gt 6144 ]; then
        WARNING_SUBJECT="⚠️ WARNING: High Redis Memory on ${DOMAIN}"
        WARNING_BODY="Redis memory usage is high and may need attention.

Domain: ${DOMAIN}
Memory Used: ${REDIS_MEMORY}
Connected Clients: ${REDIS_CLIENTS}
Time: ${TIMESTAMP}

Consider:
- Reviewing Redis memory policy
- Checking for memory leaks
- Clearing old cache entries

Current Redis stats available at:
https://${DOMAIN}/horizon/dashboard
"
        echo "$WARNING_BODY" | mail -s "$WARNING_SUBJECT" "$ALERT_EMAIL"
    fi
    
    # Log healthy status once per day (to confirm monitoring is working)
    HOUR=$(date +%H)
    MIN=$(date +%M)
    if [ "$HOUR" == "09" ] && [ "$MIN" -lt "05" ]; then
        HEALTH_SUBJECT="✅ Daily Health Report: ${DOMAIN}"
        HEALTH_BODY="Daily health check report for ${DOMAIN}

Services Status:
- Redis: RUNNING
- Memory: ${REDIS_MEMORY}
- Clients: ${REDIS_CLIENTS}
- Octane: $(pgrep -f octane > /dev/null && echo 'RUNNING' || echo 'STOPPED')
- Horizon: $(pgrep -f horizon > /dev/null && echo 'RUNNING' || echo 'STOPPED')

Monitoring is active and all services are healthy.

Dashboard: https://${DOMAIN}/horizon/dashboard
Time: ${TIMESTAMP}
"
        echo "$HEALTH_BODY" | mail -s "$HEALTH_SUBJECT" "$ALERT_EMAIL"
    fi
fi

