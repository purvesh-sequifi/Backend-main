#!/bin/bash

# Start multiple queue workers for parallel BigQuery job processing
# This script launches multiple dedicated workers for different queue types

echo "Starting parallel queue workers for BigQuery processing..."

# Create logs directory if it doesn't exist
LOGS_DIR="./storage/logs/queue-workers"
mkdir -p "$LOGS_DIR"

# Define timestamp for log files
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Function to check if any queue workers are already running
check_existing_workers() {
  local queue=$1
  local count=$(ps aux | grep "[q]ueue:work --queue=$queue" | wc -l)
  echo "$count existing workers for queue: $queue"
  return $count
}

# Function to start workers for a specific queue
start_workers() {
  local queue=$1
  local count=$2
  local mem_limit=$3
  
  echo "Starting $count workers for $queue queue..."
  
  for (( i=1; i<=$count; i++ ))
  do
     # Create a log file for each worker
     local log_file="$LOGS_DIR/${queue}_worker_${i}_${TIMESTAMP}.log"
     touch "$log_file"
     
     # Start the worker with memory limit and redirect output to log file
     php -d memory_limit=$mem_limit artisan queue:work \
       --queue=$queue \
       --tries=3 \
       --delay=3 \
       --sleep=1 \
       --timeout=300 \
       --max-jobs=500 \
       --max-time=3600 > "$log_file" 2>&1 &
     
     local pid=$!
     echo "  Worker $i started (PID: $pid) - Log: $log_file"
     
     # Give a small delay between starting workers to prevent resource contention
     sleep 0.5
  done
}

# Kill any existing queue workers if requested
if [ "$1" == "--restart" ]; then
  echo "Stopping existing queue workers..."
  pkill -f "queue:work"
  sleep 2 # Give workers time to shut down gracefully
  echo "Existing workers stopped"
fi

# Number of workers to start for each queue
DIAGNOSTICS_WORKERS=4
SYNC_WORKERS=4
DEFAULT_WORKERS=2

# Memory limits for different worker types
DIAGNOSTICS_MEM="512M"
SYNC_MEM="512M"
DEFAULT_MEM="256M"

# Start workers for each queue type
start_workers "bigquery-diagnostics" $DIAGNOSTICS_WORKERS $DIAGNOSTICS_MEM
start_workers "BigQueryUserImport" $SYNC_WORKERS $SYNC_MEM
start_workers "default" $DEFAULT_WORKERS $DEFAULT_MEM

# Count and display worker statistics
TOTAL_WORKERS=$(ps aux | grep queue:work | grep -v grep | wc -l)
DIAG_ACTIVE=$(ps aux | grep "[q]ueue:work --queue=bigquery-diagnostics" | wc -l)
SYNC_ACTIVE=$(ps aux | grep "[q]ueue:work --queue=BigQueryUserImport" | wc -l)
DEFAULT_ACTIVE=$(ps aux | grep "[q]ueue:work" | grep -v "--queue" | wc -l)

echo ""
echo "===== Worker Status =====" 
echo "Total active workers: $TOTAL_WORKERS"
echo "  bigquery-diagnostics: $DIAG_ACTIVE/$DIAGNOSTICS_WORKERS"
echo "  BigQueryUserImport: $SYNC_ACTIVE/$SYNC_WORKERS"
echo "  default queue: $DEFAULT_ACTIVE/$DEFAULT_WORKERS"

echo ""
echo "Worker logs saved to: $LOGS_DIR"
echo ""
echo "To view active workers, run: ps aux | grep queue:work"
echo "To stop all workers, run: pkill -f \"queue:work\""
echo "To restart workers, run: $0 --restart"

echo ""
echo "Use the following commands to run BigQuery operations:"
echo "php artisan bigquery:diagnose --parallel --batch-size=25"
echo "php artisan bigquery:diagnose-results"
echo ""
