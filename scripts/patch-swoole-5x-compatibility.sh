#!/bin/bash
# Swoole 5.x/6.x Compatibility Patch for Laravel Octane
# Fixes task callback signature changes in Swoole 5.x and newer
set -e

FILE="vendor/laravel/octane/bin/swoole-server"

if [ ! -f "$FILE" ]; then
  echo "⚠️  Swoole server file not found"
  exit 0
fi

# Backup
cp "$FILE" "$FILE.backup" 2>/dev/null || true

# Apply patch using PHP
php << 'PHP'
<?php
$file = 'vendor/laravel/octane/bin/swoole-server';
$content = file_get_contents($file);

// Fix task callback
$old = '$server->on(\'task\', fn (Server $server, int $taskId, int $fromWorkerId, $data) =>
    $data === \'octane-tick\'
            ? $workerState->worker->handleTick()
            : $workerState->worker->handleTask($data)
);';

$new = '$server->on(\'task\', function ($task) use ($workerState) {
    // Swoole 5.x/6.x compatibility: Handle both Task object and direct data
    // In Swoole 5.x+, callback receives Swoole\Server\Task with ->data property
    // CRITICAL: Never pass Swoole objects to handleTask - it expects closures!
    if (is_object($task)) {
        // If this is a Swoole Task object, extract data property
        if (property_exists($task, \'data\')) {
            $data = $task->data;
        } else {
            // Should never happen, but log and skip if malformed task
            error_log(\'[Octane] Warning: Task object missing data property. Type: \' . get_class($task));
            return null;
        }
    } else {
        // Direct data (for backwards compatibility)
        $data = $task;
    }
    
    return $data === \'octane-tick\'
            ? $workerState->worker->handleTick()
            : $workerState->worker->handleTask($data);
});';

$content = str_replace($old, $new, $content);

// Fix finish callback  
$old_finish = '$server->on(\'finish\', fn (Server $server, int $taskId, $result) => $result);';
$new_finish = '$server->on(\'finish\', fn ($task, $result) => $result);';
$content = str_replace($old_finish, $new_finish, $content);

file_put_contents($file, $content);
echo "✅ Swoole 5.x/6.x patch applied\n";
PHP
