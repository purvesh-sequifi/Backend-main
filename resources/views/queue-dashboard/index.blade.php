<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Queue & System Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .status-healthy { @apply bg-green-100 text-green-800; }
        .status-warning { @apply bg-yellow-100 text-yellow-800; }
        .status-critical { @apply bg-red-100 text-red-800; }
        
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-server text-blue-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Queue & System Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>
                        <span>Last refreshed: <span id="lastUpdated">--:--:--</span></span>
                    </div>
                    <div class="flex items-center">
                        <div class="h-2 w-2 bg-green-500 rounded-full mr-2 animate-pulse" id="statusIndicator"></div>
                        <span class="text-sm text-gray-600" id="connectionStatus">Connected</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- System Health Overview -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-heartbeat text-red-500 mr-2"></i>
                    System Health Overview
                </h2>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Health API Status:</span>
                    <span id="healthApiStatus" class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                        {{ $systemHealth['status'] ?? 'Unknown' }}
                    </span>
                </div>
            </div>
            
            <!-- System Health Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- CPU Usage -->
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-2 rounded-lg">
                                <i class="fas fa-microchip text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">CPU Usage</h3>
                                <p class="text-xs text-gray-500">Load Average</p>
                            </div>
                        </div>
                    </div>
                    <div id="cpuMetrics">
                        @if(isset($systemHealth['server']['cpu']) && $systemHealth['server']['cpu']['status'] === 'available')
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-2xl font-bold text-gray-900">{{ $systemHealth['server']['cpu']['percentage'] }}%</span>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        @if($systemHealth['server']['cpu']['status_level'] === 'success') bg-green-100 text-green-800
                                        @elseif($systemHealth['server']['cpu']['status_level'] === 'warning') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800 @endif">
                                        @if($systemHealth['server']['cpu']['status_level'] === 'success') Normal
                                        @elseif($systemHealth['server']['cpu']['status_level'] === 'warning') High
                                        @else Critical @endif
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div>1m: {{ $systemHealth['server']['cpu']['load_1min'] }}</div>
                                    <div>5m: {{ $systemHealth['server']['cpu']['load_5min'] }}</div>
                                    <div>15m: {{ $systemHealth['server']['cpu']['load_15min'] }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-gray-500">
                                <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                <p class="text-sm">CPU data unavailable</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Memory Usage -->
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <i class="fas fa-memory text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Memory Usage</h3>
                                <p class="text-xs text-gray-500">RAM</p>
                            </div>
                        </div>
                    </div>
                    <div id="memoryMetrics">
                        @if(isset($systemHealth['server']['memory']) && $systemHealth['server']['memory']['status'] === 'available')
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-2xl font-bold text-gray-900">{{ number_format(100 - $systemHealth['server']['memory']['free_percent'], 1) }}%</span>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        @if($systemHealth['server']['memory']['status_level'] === 'success') bg-green-100 text-green-800
                                        @elseif($systemHealth['server']['memory']['status_level'] === 'warning') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800 @endif">
                                        @if($systemHealth['server']['memory']['status_level'] === 'success') Good
                                        @elseif($systemHealth['server']['memory']['status_level'] === 'warning') Low
                                        @else Critical @endif
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div>Total: {{ $systemHealth['server']['memory']['total'] }}</div>
                                    <div>Free: {{ $systemHealth['server']['memory']['free'] }} ({{ $systemHealth['server']['memory']['free_percent'] }}%)</div>
                                    <div>Used: {{ $systemHealth['server']['memory']['used'] }}</div>
                                </div>
                            </div>
                        @elseif(isset($systemHealth['server']['memory']) && $systemHealth['server']['memory']['status'] === 'php_only')
                            <div class="space-y-2">
                                <div class="text-center">
                                    <span class="text-sm font-medium text-gray-700">PHP Memory</span>
                                </div>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div>Limit: {{ $systemHealth['server']['memory']['php_limit'] }}</div>
                                    <div>Usage: {{ $systemHealth['server']['memory']['php_usage'] }}</div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-gray-500">
                                <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                <p class="text-sm">Memory data unavailable</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Disk Usage -->
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <i class="fas fa-hdd text-purple-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Disk Usage</h3>
                                <p class="text-xs text-gray-500">Storage</p>
                            </div>
                        </div>
                    </div>
                    <div id="diskMetrics">
                        @if(isset($systemHealth['server']['disk']) && $systemHealth['server']['disk']['status'] === 'available')
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-2xl font-bold text-gray-900">{{ number_format(100 - $systemHealth['server']['disk']['percent_free'], 1) }}%</span>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        @if($systemHealth['server']['disk']['status_level'] === 'success') bg-green-100 text-green-800
                                        @elseif($systemHealth['server']['disk']['status_level'] === 'warning') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800 @endif">
                                        @if($systemHealth['server']['disk']['status_level'] === 'success') Good
                                        @elseif($systemHealth['server']['disk']['status_level'] === 'warning') Low
                                        @else Critical @endif
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div>Total: {{ $systemHealth['server']['disk']['total'] }}</div>
                                    <div>Free: {{ $systemHealth['server']['disk']['free'] }} ({{ $systemHealth['server']['disk']['percent_free'] }}%)</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ 100 - $systemHealth['server']['disk']['percent_free'] }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center text-gray-500">
                                <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                <p class="text-sm">Disk data unavailable</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- System Services -->
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="bg-orange-100 p-2 rounded-lg">
                                <i class="fas fa-cogs text-orange-600 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Services</h3>
                                <p class="text-xs text-gray-500">Status</p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span>Database</span>
                            <span class="px-2 py-1 rounded-full {{ $systemHealth['database'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $systemHealth['database'] ? 'OK' : 'Error' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Cache</span>
                            <span class="px-2 py-1 rounded-full {{ $systemHealth['cache'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $systemHealth['cache'] ? 'OK' : 'Error' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Redis</span>
                            @if($systemHealth['redis'] === null)
                                <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-600">Skipped</span>
                            @else
                                <span class="px-2 py-1 rounded-full {{ $systemHealth['redis'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $systemHealth['redis'] ? 'OK' : 'Error' }}
                                </span>
                            @endif
                        </div>
                        @if(isset($systemHealth['workers']))
                            <div class="flex justify-between items-center">
                                <span>Workers</span>
                                <span class="px-2 py-1 rounded-full {{ $systemHealth['workers']['healthy'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $systemHealth['workers']['running_processes'] }}/{{ $systemHealth['workers']['total_processes'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Statistics Section (existing content) -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-list-alt text-blue-500 mr-2"></i>
                    Queue Statistics
                </h2>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Overall Health:</span>
                        <span id="overallHealth" class="px-3 py-1 text-sm font-medium rounded-full 
                            @if($queueHealth === 'healthy') bg-green-100 text-green-800
                            @elseif($queueHealth === 'warning') bg-yellow-100 text-yellow-800
                            @else bg-red-100 text-red-800 @endif">
                            {{ ucfirst($queueHealth) }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="retryAllJobs()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-redo mr-2"></i>Retry All Failed
                        </button>
                        <button onclick="clearAllJobs()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            <i class="fas fa-trash mr-2"></i>Clear All Failed
                        </button>
                        <button onclick="resetAllStuckJobs()" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                            <i class="fas fa-clock mr-2"></i>Reset Stuck Jobs
                        </button>
                        <button onclick="restartWorkers()" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            <i class="fas fa-power-off mr-2"></i>Restart Workers
                        </button>
                        <button id="refreshBtn" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Performance Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Jobs Today</p>
                            <p class="text-2xl font-bold text-gray-900" id="jobs-today">-</p>
                            <p class="text-xs text-gray-400 mt-1" id="jobs-today-detail">Processed jobs in last 24h</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Avg Time</p>
                            <p class="text-2xl font-bold text-gray-900" id="avg-time">-</p>
                            <p class="text-xs text-gray-400 mt-1" id="avg-time-detail">Average processing time</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-percentage text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Success Rate</p>
                            <p class="text-2xl font-bold text-gray-900" id="success-rate">-</p>
                            <p class="text-xs text-gray-400 mt-1" id="success-rate-detail">Completed vs total jobs</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow card-hover p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-fire text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Peak Hour</p>
                            <p class="text-2xl font-bold text-gray-900" id="peak-hour">-</p>
                            <p class="text-xs text-gray-400 mt-1" id="peak-hour-detail">Busiest hour today</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queue Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Queue Status Cards -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <div class="flex items-center space-x-3">
                            <h3 class="text-lg font-semibold text-gray-900">Queue Status</h3>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-600" 
                                  title="Queue Detection Mode: {{ config('queue-dashboard.queue_detection_mode', 'worker_only') }}">
                                @switch(config('queue-dashboard.queue_detection_mode', 'worker_only'))
                                    @case('worker_only')
                                        <i class="fas fa-cogs mr-1"></i>Workers Only
                                        @break
                                    @case('active_jobs_only')
                                        <i class="fas fa-tasks mr-1"></i>Active Jobs
                                        @break
                                    @case('all_discovered')
                                        <i class="fas fa-search mr-1"></i>All Discovered
                                        @break
                                    @default
                                        <i class="fas fa-question mr-1"></i>{{ config('queue-dashboard.queue_detection_mode') }}
                                @endswitch
                            </span>
                        </div>
                        <button onclick="loadWorkerStatus()" class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 transition">
                            <i class="fas fa-sync-alt mr-1"></i>Check Workers
                        </button>
                    </div>
                    <div class="p-6">
                        <div id="queue-stats" class="space-y-4">
                            <!-- Queue cards will be inserted here -->
                        </div>
                    </div>
                </div>

                <!-- Worker Status Cards -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-cogs text-orange-500 mr-2"></i>
                            Worker Status
                        </h3>
                        <div class="flex items-center space-x-2">
                            <span id="worker-health-badge" class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">
                                Checking...
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div id="worker-status" class="space-y-4">
                            <!-- Worker status will be inserted here -->
                        </div>
                    </div>
                </div>

                <!-- Performance Chart -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Performance History</h3>
                        <select id="chart-timeframe" class="text-sm border rounded px-2 py-1" onchange="updateChartTimeframe()">
                            <option value="24">Last 24 hours</option>
                            <option value="48">Last 48 hours</option>
                            <option value="72">Last 3 days</option>
                        </select>
                    </div>
                    <div class="p-6">
                        <canvas id="performance-chart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Performance Analytics -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-analytics text-purple-500 mr-2"></i>
                    Performance Analytics
                </h2>
                <select id="analytics-timeframe" class="text-sm border rounded px-2 py-1" onchange="loadPerformanceAnalytics()">
                    <option value="1">Last 24 hours</option>
                    <option value="3">Last 3 days</option>
                    <option value="7" selected>Last 7 days</option>
                    <option value="30">Last 30 days</option>
                </select>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-6">
                <!-- Queue Performance -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Queue Performance</h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left text-xs font-medium text-gray-500 uppercase py-2">Queue</th>
                                        <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Total</th>
                                        <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Success Rate</th>
                                        <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Avg Time</th>
                                    </tr>
                                </thead>
                                <tbody id="queue-performance-table">
                                    <!-- Queue performance data will be inserted here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Job Class Performance -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Top Job Classes</h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left text-xs font-medium text-gray-500 uppercase py-2">Job Class</th>
                                        <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Total</th>
                                        <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Success Rate</th>
                                        <th class="text-right text-xs font-medium text-gray-500 uppercase py-2">Avg Time</th>
                                    </tr>
                                </thead>
                                <tbody id="job-class-performance-table">
                                    <!-- Job class performance data will be inserted here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Trend Chart -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Daily Job Trends</h3>
                </div>
                <div class="p-6">
                    <canvas id="daily-trends-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Scheduled Cron Jobs Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-clock text-green-500 mr-2"></i>
                    Scheduled Cron Jobs
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 ml-2" id="environment-badge">
                        {{ ucfirst(app()->environment()) }}
                    </span>
                </h2>
                <button onclick="loadCronJobs()" class="px-3 py-1 text-sm bg-gray-100 text-gray-600 rounded hover:bg-gray-200 transition">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh
                </button>
            </div>

            <!-- Cron Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-lg">
                            <i class="fas fa-tasks text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">Total Jobs</h3>
                            <p class="text-2xl font-bold text-gray-900" id="cron-total-jobs">-</p>
                            <p class="text-xs text-gray-400 mt-1">Scheduled commands</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-lg">
                            <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">Protected Jobs</h3>
                            <p class="text-2xl font-bold text-gray-900" id="cron-protected-jobs">-</p>
                            <p class="text-xs text-gray-400 mt-1">With overlap protection</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-lg">
                            <i class="fas fa-play text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">Background Jobs</h3>
                            <p class="text-2xl font-bold text-gray-900" id="cron-background-jobs">-</p>
                            <p class="text-xs text-gray-400 mt-1">Run in background</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-2 rounded-lg">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">Next Job</h3>
                            <p class="text-sm font-bold text-gray-900" id="cron-next-job">-</p>
                            <p class="text-xs text-gray-400 mt-1" id="cron-next-time">Next execution</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cron Jobs Table -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Scheduled Commands</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Command</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequency</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Run</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Features</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody id="cron-jobs-table" class="bg-white divide-y divide-gray-200">
                            <!-- Cron jobs will be inserted here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Failed Jobs Section -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Failed Jobs</h3>
                    <div class="flex space-x-2">
                        <button onclick="toggleFailedJobsFilters()" class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 transition">
                            <i class="fas fa-filter mr-1"></i>Filters
                        </button>
                        <button onclick="refreshFailedJobs()" class="px-3 py-1 text-sm bg-gray-100 text-gray-600 rounded hover:bg-gray-200 transition">
                            <i class="fas fa-sync-alt mr-1"></i>Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Search and Filter Panel -->
                <div id="failed-jobs-filters" class="hidden">
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                <input type="text" id="failed-jobs-search" placeholder="Search failure reason..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Error Type</label>
                                <select id="failed-jobs-error-type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Types</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Error Category</label>
                                <select id="failed-jobs-error-category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Job Class</label>
                                <select id="failed-jobs-job-class" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Job Classes</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Queue</label>
                                <select id="failed-jobs-queue" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Queues</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Retryable</label>
                                <select id="failed-jobs-retryable" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All</option>
                                    <option value="1">Retryable</option>
                                    <option value="0">Non-Retryable</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 flex space-x-2">
                            <button onclick="applyFailedJobsFilters()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                                Apply Filters
                            </button>
                            <button onclick="clearFailedJobsFilters()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exception</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="failed-jobs-table" class="bg-white divide-y divide-gray-200">
                        <!-- Failed jobs will be inserted here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500" id="pagination-info">
                        <!-- Pagination info will be inserted here -->
                    </div>
                    <div class="flex space-x-2" id="pagination-controls">
                        <!-- Pagination controls will be inserted here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Stuck Jobs Section -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-clock text-yellow-500 mr-2"></i>
                        Stuck Jobs
                    </h3>
                    <span id="stuck-jobs-count" class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                        0 stuck
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <select id="stuck-hours-filter" class="text-sm border rounded px-2 py-1" onchange="loadStuckJobs()">
                        <option value="2">Stuck for 2+ hours</option>
                        <option value="4" selected>Stuck for 4+ hours</option>
                        <option value="8">Stuck for 8+ hours</option>
                        <option value="24">Stuck for 24+ hours</option>
                    </select>
                    <button onclick="loadStuckJobs()" class="px-3 py-1 text-sm bg-gray-100 text-gray-600 rounded hover:bg-gray-200 transition">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                    <button onclick="resetAllStuckJobs()" class="px-3 py-1 text-sm bg-green-100 text-green-600 rounded hover:bg-green-200 transition">
                        <i class="fas fa-redo mr-1"></i>Reset All
                    </button>
                    <button onclick="clearAllStuckJobs()" class="px-3 py-1 text-sm bg-red-100 text-red-600 rounded hover:bg-red-200 transition">
                        <i class="fas fa-trash mr-1"></i>Delete All
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Queue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Started/Reserved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stuck Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="stuck-jobs-table" class="bg-white divide-y divide-gray-200">
                        <!-- Stuck jobs will be inserted here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Stuck Jobs Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500" id="stuck-pagination-info">
                        <!-- Pagination info will be inserted here -->
                    </div>
                    <div class="flex space-x-2" id="stuck-pagination-controls">
                        <!-- Pagination controls will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
        <!-- Toast notifications will appear here -->
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 flex items-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-3"></div>
            <span class="text-gray-700">Processing...</span>
        </div>
    </div>

    <script>
        // Dashboard JavaScript
        let performanceChart;
        let dailyTrendsChart;
        let currentFailedJobsPage = 1;
        let currentStuckJobsPage = 1;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCSRF();
            loadDashboard();
            initializePerformanceChart();
            
            // Refresh button event listener
            document.getElementById('refreshBtn').addEventListener('click', loadDashboard);
        });

        function initializeCSRF() {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            window.axios = {
                defaults: {
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                }
            };
        }

        async function loadDashboard() {
            try {
                const response = await fetch('/queue-dashboard/api/statistics', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    await handleApiError(response, 'Failed to load dashboard statistics');
                    return;
                }
                
                const data = await response.json();
                
                updatePerformanceMetrics(data.performance);
                updateQueueStats(data.stats);
                updateSystemHealth(data.system_health);
                updateStuckJobsCount(data.stuck_jobs_count);
                updateTimestamp(data.timestamp);
                
                // Load failed jobs
                await loadFailedJobs();
                
                // Load stuck jobs
                await loadStuckJobs();
                
                // Load performance history
                await loadPerformanceHistory();
                
                // Load performance analytics
                await loadPerformanceAnalytics();
                
                // Load worker status
                await loadWorkerStatus();
                
                // Load cron jobs
                await loadCronJobs();
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
                showToast('Error loading dashboard data', 'error');
            }
        }

        function updatePerformanceMetrics(performance) {
            if (!performance) return;
            
            const jobsTodayEl = document.getElementById('jobs-today');
            const avgTimeEl = document.getElementById('avg-time'); 
            const successRateEl = document.getElementById('success-rate');
            const peakHourEl = document.getElementById('peak-hour');
            
            if (jobsTodayEl) {
                jobsTodayEl.textContent = performance.total_jobs_today ? performance.total_jobs_today.toLocaleString() : '0';
            }
            
            if (avgTimeEl) {
                avgTimeEl.textContent = performance.avg_processing_time ? performance.avg_processing_time.toFixed(2) + 's' : '0s';
            }
            
            if (successRateEl) {
                successRateEl.textContent = performance.success_rate ? performance.success_rate + '%' : '0%';
            }
            
            if (peakHourEl) {
                peakHourEl.textContent = performance.peak_hour_today || 'N/A';
            }
        }

        function updateSystemHealth(systemHealth) {
            // Update health API status
            const healthApiStatus = document.getElementById('healthApiStatus');
            if (healthApiStatus) {
                healthApiStatus.textContent = systemHealth.status || 'Unknown';
                healthApiStatus.className = 'px-2 py-1 text-xs font-medium rounded-full ' + 
                    (systemHealth.status === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
            }

            // Update CPU metrics
            updateCpuMetrics(systemHealth.server?.cpu);
            
            // Update Memory metrics
            updateMemoryMetrics(systemHealth.server?.memory);
            
            // Update Disk metrics
            updateDiskMetrics(systemHealth.server?.disk);
        }

        function updateCpuMetrics(cpuData) {
            const cpuContainer = document.getElementById('cpuMetrics');
            if (!cpuContainer) return;

            if (cpuData && cpuData.status === 'available') {
                const statusClass = getHealthStatusClass(cpuData.status_level);
                const statusText = getHealthStatusText(cpuData.status_level);
                
                cpuContainer.innerHTML = `
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-2xl font-bold text-gray-900">${cpuData.percentage}%</span>
                            <span class="px-2 py-1 text-xs rounded-full ${statusClass}">
                                ${statusText}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>1m: ${cpuData.load_1min}</div>
                            <div>5m: ${cpuData.load_5min}</div>
                            <div>15m: ${cpuData.load_15min}</div>
                        </div>
                    </div>
                `;
            } else {
                cpuContainer.innerHTML = `
                    <div class="text-center text-gray-500">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p class="text-sm">CPU data unavailable</p>
                    </div>
                `;
            }
        }

        function updateMemoryMetrics(memoryData) {
            const memoryContainer = document.getElementById('memoryMetrics');
            if (!memoryContainer) return;

            if (memoryData && memoryData.status === 'available') {
                const usedPercent = (100 - memoryData.free_percent).toFixed(1);
                const statusClass = getHealthStatusClass(memoryData.status_level);
                const statusText = getHealthStatusText(memoryData.status_level);
                
                memoryContainer.innerHTML = `
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-2xl font-bold text-gray-900">${usedPercent}%</span>
                            <span class="px-2 py-1 text-xs rounded-full ${statusClass}">
                                ${statusText}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>Total: ${memoryData.total}</div>
                            <div>Free: ${memoryData.free} (${memoryData.free_percent}%)</div>
                            <div>Used: ${memoryData.used}</div>
                        </div>
                    </div>
                `;
            } else if (memoryData && memoryData.status === 'php_only') {
                memoryContainer.innerHTML = `
                    <div class="space-y-2">
                        <div class="text-center">
                            <span class="text-sm font-medium text-gray-700">PHP Memory</span>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>Limit: ${memoryData.php_limit}</div>
                            <div>Usage: ${memoryData.php_usage}</div>
                        </div>
                    </div>
                `;
            } else {
                memoryContainer.innerHTML = `
                    <div class="text-center text-gray-500">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p class="text-sm">Memory data unavailable</p>
                    </div>
                `;
            }
        }

        function updateDiskMetrics(diskData) {
            const diskContainer = document.getElementById('diskMetrics');
            if (!diskContainer) return;

            if (diskData && diskData.status === 'available') {
                const usedPercent = (100 - diskData.percent_free).toFixed(1);
                const statusClass = getHealthStatusClass(diskData.status_level);
                const statusText = getHealthStatusText(diskData.status_level);
                
                diskContainer.innerHTML = `
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-2xl font-bold text-gray-900">${usedPercent}%</span>
                            <span class="px-2 py-1 text-xs rounded-full ${statusClass}">
                                ${statusText}
                            </span>
                        </div>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>Total: ${diskData.total}</div>
                            <div>Free: ${diskData.free} (${diskData.percent_free}%)</div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: ${usedPercent}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                diskContainer.innerHTML = `
                    <div class="text-center text-gray-500">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p class="text-sm">Disk data unavailable</p>
                    </div>
                `;
            }
        }

        function getHealthStatusClass(level) {
            const classes = {
                success: 'bg-green-100 text-green-800',
                warning: 'bg-yellow-100 text-yellow-800',
                danger: 'bg-red-100 text-red-800',
                info: 'bg-blue-100 text-blue-800'
            };
            return classes[level] || 'bg-gray-100 text-gray-800';
        }

        function getHealthStatusText(level) {
            const texts = {
                success: 'Good',
                warning: 'Warning',
                danger: 'Critical',
                info: 'Info'
            };
            return texts[level] || 'Unknown';
        }

        function updateQueueStats(stats) {
            const container = document.getElementById('queue-stats');
            container.innerHTML = '';
            
            Object.values(stats).forEach(queue => {
                const statusClass = `status-${queue.status}`;
                const queueCard = `
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3 ${getStatusColor(queue.status)}"></div>
                            <div>
                                <p class="font-medium text-gray-900">${queue.queue}</p>
                                <p class="text-sm text-gray-500">${queue.total} jobs • ${queue.failed_24h} failed today</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-lg font-bold text-gray-900">${queue.pending}</p>
                                <p class="text-xs text-gray-500">Pending</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-bold text-blue-600">${queue.processing}</p>
                                <p class="text-xs text-gray-500">Processing</p>
                            </div>
                            <button onclick="clearQueue('${queue.queue}')" class="px-3 py-1 text-sm bg-red-100 text-red-600 rounded hover:bg-red-200 transition">
                                Clear
                            </button>
                        </div>
                    </div>
                `;
                container.innerHTML += queueCard;
            });
        }

        // Worker Status Functions
        async function loadWorkerStatus() {
            try {
                const response = await makeApiRequest('/queue-dashboard/api/worker-status');
                if (response) {
                    const data = await response.json();
                    updateWorkerStatus(data);
                } else {
                    showWorkerStatusError('Failed to load worker status');
                }
            } catch (error) {
                console.error('Error loading worker status:', error);
                showWorkerStatusError('Error connecting to worker monitoring');
            }
        }

        function updateWorkerStatus(data) {
            const container = document.getElementById('worker-status');
            const healthBadge = document.getElementById('worker-health-badge');
            
            // Update health badge
            const healthClass = getWorkerHealthClass(data.status);
            healthBadge.className = `px-2 py-1 text-xs font-medium rounded-full ${healthClass}`;
            healthBadge.textContent = getWorkerHealthText(data.status);
            
            // Show summary
            container.innerHTML = '';
            
            // Worker Summary Card
            const summaryCard = `
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-lg p-4 border border-orange-200">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-server text-orange-500 mr-2"></i>
                            Worker Overview
                        </h4>
                        <span class="text-xs text-gray-500">${formatTimestamp(data.scan_timestamp)}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-orange-600">${data.summary.total_running || 0}</p>
                            <p class="text-xs text-gray-600">Running Processes</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-700">${data.summary.total_configured || 0}</p>
                            <p class="text-xs text-gray-600">Configured Workers</p>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-between items-center">
                        <span class="text-sm text-gray-600">Health Score:</span>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-orange-500 h-2 rounded-full transition-all duration-500" 
                                     style="width: ${data.summary.health_score || 0}%"></div>
                            </div>
                            <span class="text-sm font-medium">${data.summary.health_score || 0}%</span>
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML += summaryCard;
            
            // Worker Distribution by Queue
            if (data.worker_analysis && data.worker_analysis.worker_distribution) {
                const distributionCard = createWorkerDistributionCard(data.worker_analysis.worker_distribution);
                container.innerHTML += distributionCard;
            }
            
            // Health Issues and Recommendations
            if (data.health_details && data.health_details.issues && data.health_details.issues.length > 0) {
                const issuesCard = createHealthIssuesCard(data.health_details);
                container.innerHTML += issuesCard;
            }
            
            // Performance Metrics
            if (data.worker_analysis && data.worker_analysis.performance_metrics) {
                const performanceCard = createWorkerPerformanceCard(data.worker_analysis.performance_metrics);
                container.innerHTML += performanceCard;
            }
        }

        function createWorkerDistributionCard(distribution) {
            let distributionHTML = '';
            
            for (const [queue, workers] of Object.entries(distribution)) {
                const workerCount = workers.length;
                const cpuAvg = workers.reduce((sum, w) => sum + (w.cpu_usage || 0), 0) / workerCount;
                const memoryAvg = workers.reduce((sum, w) => sum + (w.memory_usage || 0), 0) / workerCount;
                
                distributionHTML += `
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                            <span class="font-medium text-gray-900">${queue}</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <span class="text-gray-600">${workerCount} worker${workerCount !== 1 ? 's' : ''}</span>
                            ${cpuAvg > 0 ? `<span class="text-xs text-gray-500">CPU: ${cpuAvg.toFixed(1)}%</span>` : ''}
                            ${memoryAvg > 0 ? `<span class="text-xs text-gray-500">RAM: ${memoryAvg.toFixed(1)}%</span>` : ''}
                        </div>
                    </div>
                `;
            }
            
            return `
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-network-wired text-blue-500 mr-2"></i>
                        Worker Distribution
                    </h4>
                    <div class="space-y-1">
                        ${distributionHTML || '<p class="text-sm text-gray-500">No worker distribution data available</p>'}
                    </div>
                </div>
            `;
        }

        function createHealthIssuesCard(healthDetails) {
            const issuesHTML = healthDetails.issues.map(issue => `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                    <span class="text-sm text-gray-700">${issue}</span>
                </div>
            `).join('');
            
            const recommendationsHTML = healthDetails.recommendations.map(rec => `
                <div class="flex items-center">
                    <i class="fas fa-lightbulb text-blue-500 mr-2"></i>
                    <span class="text-sm text-gray-700">${rec}</span>
                </div>
            `).join('');
            
            return `
                <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-exclamation-circle text-yellow-500 mr-2"></i>
                        Health Issues
                    </h4>
                    <div class="space-y-2 mb-3">
                        ${issuesHTML}
                    </div>
                    ${recommendationsHTML ? `
                        <div class="border-t border-yellow-200 pt-3">
                            <p class="text-sm font-medium text-gray-900 mb-2">Recommendations:</p>
                            <div class="space-y-1">
                                ${recommendationsHTML}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        function createWorkerPerformanceCard(performance) {
            return `
                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <h4 class="font-semibold text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-tachometer-alt text-green-500 mr-2"></i>
                        Performance Metrics
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <p class="text-lg font-bold text-green-600">${performance.avg_cpu_usage || 0}%</p>
                            <p class="text-xs text-gray-600">Avg CPU Usage</p>
                        </div>
                        <div class="text-center">
                            <p class="text-lg font-bold text-green-600">${performance.avg_memory_usage || 0}%</p>
                            <p class="text-xs text-gray-600">Avg Memory Usage</p>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <p class="text-sm text-gray-600">
                            Total Memory: <span class="font-medium">${performance.total_memory_mb || 0} MB</span>
                        </p>
                    </div>
                </div>
            `;
        }

        function showWorkerStatusError(message) {
            const container = document.getElementById('worker-status');
            const healthBadge = document.getElementById('worker-health-badge');
            
            healthBadge.className = 'px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800';
            healthBadge.textContent = 'Error';
            
            container.innerHTML = `
                <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        <div>
                            <p class="font-medium text-red-900">Worker Status Unavailable</p>
                            <p class="text-sm text-red-700">${message}</p>
                        </div>
                    </div>
                </div>
            `;
        }

        function getWorkerHealthClass(status) {
            const classes = {
                healthy: 'bg-green-100 text-green-800',
                warning: 'bg-yellow-100 text-yellow-800',
                critical: 'bg-red-100 text-red-800',
                error: 'bg-red-100 text-red-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        }

        function getWorkerHealthText(status) {
            const texts = {
                healthy: 'Healthy',
                warning: 'Warning',
                critical: 'Critical',
                error: 'Error'
            };
            return texts[status] || 'Unknown';
        }

        function formatTimestamp(timestamp) {
            try {
                return new Date(timestamp).toLocaleTimeString();
            } catch (e) {
                return 'Unknown';
            }
        }

        function getStatusColor(status) {
            const colors = {
                healthy: 'bg-green-500',
                warning: 'bg-yellow-500',
                critical: 'bg-red-500'
            };
            return colors[status] || 'bg-gray-500';
        }

        function updateTimestamp(timestamp) {
            document.getElementById('lastUpdated').textContent = timestamp;
        }



        async function loadFailedJobs(page = 1) {
            try {
                const response = await fetch(`/queue-dashboard/api/failed-jobs?page=${page}`);
                const data = await response.json();
                
                updateFailedJobsTable(data.jobs);
                updatePagination(data.pagination);
                
                currentFailedJobsPage = page;
                
            } catch (error) {
                console.error('Error loading failed jobs:', error);
                showToast('Error loading failed jobs', 'error');
            }
        }

        async function loadFailedJobsWithFilters(page = 1) {
            try {
                const params = new URLSearchParams();
                params.append('page', page);
                
                const search = document.getElementById('failed-jobs-search').value;
                const errorType = document.getElementById('failed-jobs-error-type').value;
                const errorCategory = document.getElementById('failed-jobs-error-category').value;
                const jobClass = document.getElementById('failed-jobs-job-class').value;
                const queue = document.getElementById('failed-jobs-queue').value;
                const retryable = document.getElementById('failed-jobs-retryable').value;
                
                if (search) params.append('search', search);
                if (errorType) params.append('error_type', errorType);
                if (errorCategory) params.append('error_category', errorCategory);
                if (jobClass) params.append('job_class', jobClass);
                if (queue) params.append('queue', queue);
                if (retryable) params.append('is_retryable', retryable);
                
                const response = await fetch(`/queue-dashboard/api/failed-jobs-with-details?${params}`);
                const data = await response.json();
                
                updateFailedJobsTableWithDetails(data.jobs);
                updatePagination(data.pagination);
                
                // Update filter options if provided
                if (data.filters) {
                    updateFilterOptions(data.filters);
                }
                
                currentFailedJobsPage = page;
                
            } catch (error) {
                console.error('Error loading failed jobs with filters:', error);
                showToast('Error loading failed jobs', 'error');
            }
        }

        function updateFailedJobsTableWithDetails(jobs) {
            const tbody = document.getElementById('failed-jobs-table');
            
            if (jobs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                            <p>No failed jobs found!</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = jobs.map(job => {
                const enhancedInfo = job.enhanced_info || {};
                const errorTypeBadge = enhancedInfo.error_type ? `<span class="px-2 py-1 text-xs rounded-full ${getErrorTypeBadgeColor(enhancedInfo.error_type)}">${enhancedInfo.error_type}</span>` : '';
                const retryableBadge = enhancedInfo.is_retryable !== null ? `<span class="px-2 py-1 text-xs rounded-full ${enhancedInfo.is_retryable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${enhancedInfo.is_retryable ? 'Retryable' : 'Non-Retryable'}</span>` : '';
                
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${job.job_class}</div>
                            <div class="text-xs text-gray-500">ID: ${job.id}</div>
                            <div class="mt-1 space-x-1">
                                ${errorTypeBadge}
                                ${retryableBadge}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">${job.queue}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div>${job.failed_at}</div>
                            <div class="text-xs">${job.failed_at_human}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                            <div class="truncate">${enhancedInfo.failure_reason || job.exception.substring(0, 100)}...</div>
                            ${enhancedInfo.memory_usage_mb ? `<div class="text-xs text-gray-400">Memory: ${enhancedInfo.memory_usage_mb} MB</div>` : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="viewJobDetails('${job.uuid}')" class="text-blue-600 hover:text-blue-900" title="View Details">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <button onclick="retryJob('${job.uuid}')" class="text-green-600 hover:text-green-900" title="Retry Job">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button onclick="deleteJob(${job.id})" class="text-red-600 hover:text-red-900" title="Delete Job">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updateFilterOptions(filters) {
            // Update error type options
            const errorTypeSelect = document.getElementById('failed-jobs-error-type');
            errorTypeSelect.innerHTML = '<option value="">All Types</option>';
            filters.error_types.forEach(type => {
                errorTypeSelect.innerHTML += `<option value="${type}">${type}</option>`;
            });
            
            // Update error category options
            const errorCategorySelect = document.getElementById('failed-jobs-error-category');
            errorCategorySelect.innerHTML = '<option value="">All Categories</option>';
            filters.error_categories.forEach(category => {
                errorCategorySelect.innerHTML += `<option value="${category}">${category}</option>`;
            });
            
            // Update job class options
            const jobClassSelect = document.getElementById('failed-jobs-job-class');
            jobClassSelect.innerHTML = '<option value="">All Job Classes</option>';
            filters.job_classes.forEach(jobClass => {
                jobClassSelect.innerHTML += `<option value="${jobClass}">${jobClass}</option>`;
            });
            
            // Update queue options
            const queueSelect = document.getElementById('failed-jobs-queue');
            queueSelect.innerHTML = '<option value="">All Queues</option>';
            filters.queues.forEach(queue => {
                queueSelect.innerHTML += `<option value="${queue}">${queue}</option>`;
            });
        }

        function toggleFailedJobsFilters() {
            const filtersPanel = document.getElementById('failed-jobs-filters');
            filtersPanel.classList.toggle('hidden');
            
            // Load filter options if showing for the first time
            if (!filtersPanel.classList.contains('hidden')) {
                loadFailedJobsWithFilters();
            }
        }

        function applyFailedJobsFilters() {
            loadFailedJobsWithFilters(1);
        }

        function clearFailedJobsFilters() {
            document.getElementById('failed-jobs-search').value = '';
            document.getElementById('failed-jobs-error-type').value = '';
            document.getElementById('failed-jobs-error-category').value = '';
            document.getElementById('failed-jobs-job-class').value = '';
            document.getElementById('failed-jobs-queue').value = '';
            document.getElementById('failed-jobs-retryable').value = '';
            
            // Reload failed jobs without filters
            loadFailedJobs(1);
        }

        function refreshFailedJobs() {
            const filtersPanel = document.getElementById('failed-jobs-filters');
            if (filtersPanel.classList.contains('hidden')) {
                loadFailedJobs(currentFailedJobsPage);
            } else {
                loadFailedJobsWithFilters(currentFailedJobsPage);
            }
        }

        function updateFailedJobsTable(jobs) {
            const tbody = document.getElementById('failed-jobs-table');
            
            if (jobs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                            <p>No failed jobs!</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = jobs.map(job => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${job.job_class}</div>
                        <div class="text-xs text-gray-500">ID: ${job.id}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">${job.queue}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div>${job.failed_at}</div>
                        <div class="text-xs">${job.failed_at_human}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                        ${job.exception.substring(0, 100)}...
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <button onclick="viewJobDetails('${job.uuid}')" class="text-blue-600 hover:text-blue-900" title="View Details">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            <button onclick="retryJob('${job.uuid}')" class="text-green-600 hover:text-green-900" title="Retry Job">
                                <i class="fas fa-redo"></i>
                            </button>
                            <button onclick="deleteJob(${job.id})" class="text-red-600 hover:text-red-900" title="Delete Job">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function updatePagination(pagination) {
            const info = document.getElementById('pagination-info');
            const controls = document.getElementById('pagination-controls');
            
            info.textContent = `Showing ${pagination.per_page * (pagination.current_page - 1) + 1} to ${Math.min(pagination.per_page * pagination.current_page, pagination.total)} of ${pagination.total} results`;
            
            let paginationHTML = '';
            
            // Previous button
            if (pagination.current_page > 1) {
                paginationHTML += `<button onclick="loadFailedJobs(${pagination.current_page - 1})" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">Previous</button>`;
            }
            
            // Page numbers
            for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
                const activeClass = i === pagination.current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50';
                paginationHTML += `<button onclick="loadFailedJobs(${i})" class="px-3 py-2 text-sm border border-gray-300 ${activeClass}">${i}</button>`;
            }
            
            // Next button
            if (pagination.current_page < pagination.last_page) {
                paginationHTML += `<button onclick="loadFailedJobs(${pagination.current_page + 1})" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">Next</button>`;
            }
            
            controls.innerHTML = paginationHTML;
        }

        async function loadPerformanceHistory() {
            try {
                const hours = document.getElementById('chart-timeframe') ? document.getElementById('chart-timeframe').value : 24;
                const response = await fetch(`/queue-dashboard/api/performance-history?hours=${hours}`);
                const data = await response.json();
                
                updatePerformanceChart(data);
                
            } catch (error) {
                console.error('Error loading performance history:', error);
            }
        }

        function initializePerformanceChart() {
            const ctx = document.getElementById('performance-chart').getContext('2d');
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Processed',
                            data: [],
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.1
                        },
                        {
                            label: 'Failed',
                            data: [],
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.1
                        },
                        {
                            label: 'Pending',
                            data: [],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }

        function updatePerformanceChart(data) {
            performanceChart.data.labels = data.map(d => d.hour);
            performanceChart.data.datasets[0].data = data.map(d => d.processed);
            performanceChart.data.datasets[1].data = data.map(d => d.failed);
            performanceChart.data.datasets[2].data = data.map(d => d.pending);
            performanceChart.update();
        }

        // Job control functions
        async function retryJob(id) {
            showLoading();
            try {
                const response = await fetch(`/queue-dashboard/api/retry-job/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadFailedJobs(currentFailedJobsPage);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Retry job error:', error);
                showToast('Error retrying job', 'error');
            } finally {
                hideLoading();
            }
        }

        async function deleteJob(id) {
            if (!confirm('Are you sure you want to delete this failed job?')) return;
            
            showLoading();
            try {
                const response = await fetch(`/queue-dashboard/api/delete-job/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadFailedJobs(currentFailedJobsPage);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Delete job error:', error);
                showToast('Error deleting job', 'error');
            } finally {
                hideLoading();
            }
        }

        async function retryAllJobs() {
            if (!confirm('Are you sure you want to retry all failed jobs?')) return;
            
            showLoading();
            try {
                const response = await fetch('/queue-dashboard/api/retry-all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadFailedJobs(1);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Retry all jobs error:', error);
                showToast('Error retrying all jobs', 'error');
            } finally {
                hideLoading();
            }
        }

        async function clearAllJobs() {
            if (!confirm('Are you sure you want to clear all failed jobs? This action cannot be undone.')) return;
            
            showLoading();
            try {
                const response = await fetch('/queue-dashboard/api/clear-all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadFailedJobs(1);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Clear all jobs error:', error);
                showToast('Error clearing all jobs', 'error');
            } finally {
                hideLoading();
            }
        }

        async function clearQueue(queueName) {
            if (!confirm(`Are you sure you want to clear all jobs from the '${queueName}' queue?`)) return;
            
            showLoading();
            try {
                const response = await fetch('/queue-dashboard/api/clear-queue', {
                    method: 'POST',
                    headers: window.axios.defaults.headers,
                    body: JSON.stringify({ queue: queueName })
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadDashboard();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error clearing queue', 'error');
            } finally {
                hideLoading();
            }
        }

        async function restartWorkers() {
            if (!confirm('Are you sure you want to restart queue workers? This is a critical operation that affects the entire queue system.')) return;
            
            showLoading();
            try {
                const response = await makeApiRequest('/queue-dashboard/api/restart-workers', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (response) {
                    const data = await response.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                    } else {
                        showToast(data.message, 'error');
                    }
                }
            } catch (error) {
                showToast('Error restarting workers', 'error');
            } finally {
                hideLoading();
            }
        }

        function refreshFailedJobs() {
            loadFailedJobs(currentFailedJobsPage);
        }

        function updateStuckJobsCount(count) {
            const countElement = document.getElementById('stuck-jobs-count');
            if (countElement) {
                countElement.textContent = `${count} stuck`;
                countElement.className = count > 0 
                    ? 'px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800'
                    : 'px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800';
            }
        }

        function updateStuckJobsSummary(summary) {
            // Find the stuck-hours-filter element to locate the filter container
            const filterElement = document.getElementById('stuck-hours-filter');
            if (!filterElement) {
                console.warn('stuck-hours-filter element not found');
                return;
            }
            
            // Navigate up to find the filter container
            const filterContainer = filterElement.parentElement && filterElement.parentElement.parentElement;
            
            if (summary && filterContainer) {
                // Check if summary already exists
                let summaryElement = document.getElementById('stuck-jobs-summary');
                if (!summaryElement) {
                    summaryElement = document.createElement('div');
                    summaryElement.id = 'stuck-jobs-summary';
                    summaryElement.className = 'mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg';
                    
                    // Insert after the filter controls
                    if (filterContainer.nextSibling) {
                        filterContainer.parentNode.insertBefore(summaryElement, filterContainer.nextSibling);
                    } else {
                        filterContainer.parentNode.appendChild(summaryElement);
                    }
                }
                
                summaryElement.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-1">
                            <i class="fas fa-info-circle text-blue-500"></i>
                            <span class="text-sm font-medium text-blue-900">Stuck Jobs Summary:</span>
                        </div>
                        <div class="flex items-center space-x-4 text-xs">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-1"></div>
                                <span class="text-gray-600">Queue Jobs: <strong>${summary.queue_stuck_jobs || 0}</strong></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mr-1"></div>
                                <span class="text-gray-600">Progress Logs: <strong>${summary.progress_stuck_jobs || 0}</strong></span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-yellow-500 rounded-full mr-1"></div>
                                <span class="text-gray-600">Total: <strong>${summary.total_stuck_jobs || 0}</strong></span>
                            </div>
                        </div>
                    </div>
                    ${(summary.total_stuck_jobs && summary.total_stuck_jobs > 0) ? `
                        <div class="mt-2 text-xs text-blue-800">
                            <p><strong>Queue Jobs:</strong> Jobs reserved but not processing (can be reset to retry)</p>
                            <p><strong>Progress Logs:</strong> Jobs in processing state but stalled (will be marked as failed when reset)</p>
                        </div>
                    ` : ''}
                `;
            }
        }

        async function loadStuckJobs(page = 1) {
            try {
                const hoursFilter = document.getElementById('stuck-hours-filter').value;
                const response = await fetch(`/queue-dashboard/api/stuck-jobs?page=${page}&hours=${hoursFilter}`);
                const data = await response.json();
                
                updateStuckJobsTable(data.jobs);
                updateStuckPagination(data.pagination);
                updateStuckJobsSummary(data.summary);
                
                currentStuckJobsPage = page;
                
            } catch (error) {
                console.error('Error loading stuck jobs:', error);
                showToast('Error loading stuck jobs', 'error');
            }
        }

        function updateStuckJobsTable(jobs) {
            const tbody = document.getElementById('stuck-jobs-table');
            
            if (jobs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                            <p>No stuck jobs!</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = jobs.map(job => {
                const isProgressLog = job.type === 'progress_log';
                const typeColor = isProgressLog ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                const typeText = isProgressLog ? 'Progress Log' : 'Queue Job';
                
                // Handle different date formats for different job types
                const dateValue = isProgressLog ? job.started_at : job.reserved_at;
                const dateHuman = isProgressLog ? job.started_at_human : job.reserved_at_human;
                
                // Progress information
                const progressInfo = isProgressLog 
                    ? `${job.progress || 0}% ${job.processed_records ? `(${job.processed_records}/${job.total_records || '?'})` : ''}`
                    : `${job.attempts || 0} attempts`;
                
                // Status message
                const statusMessage = job.status_message || 'Unknown';
                const currentOperation = isProgressLog && job.current_operation ? job.current_operation : '';
                
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${job.job_class}</div>
                            <div class="text-xs text-gray-500">ID: ${isProgressLog ? (job.job_id || job.id) : job.id}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full ${typeColor}">${typeText}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">${job.queue}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div>${dateValue}</div>
                            <div class="text-xs">${dateHuman}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-yellow-600">${job.stuck_duration_human}</div>
                            <div class="text-xs text-gray-500">${job.stuck_duration} minutes</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div>${progressInfo}</div>
                            ${currentOperation ? `<div class="text-xs text-gray-500">${currentOperation}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${statusMessage}</div>
                            ${job.message ? `<div class="text-xs text-gray-500 max-w-xs truncate" title="${job.message}">${job.message}</div>` : ''}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="resetStuckJob(${job.id}, '${job.type}')" class="text-green-600 hover:text-green-900" title="Reset job">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <button onclick="deleteStuckJob(${job.id}, '${job.type}')" class="text-red-600 hover:text-red-900" title="Delete job permanently">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updateStuckPagination(pagination) {
            const info = document.getElementById('stuck-pagination-info');
            const controls = document.getElementById('stuck-pagination-controls');
            
            info.textContent = `Showing ${pagination.per_page * (pagination.current_page - 1) + 1} to ${Math.min(pagination.per_page * pagination.current_page, pagination.total)} of ${pagination.total} results`;
            
            let paginationHTML = '';
            
            // Previous button
            if (pagination.current_page > 1) {
                paginationHTML += `<button onclick="loadStuckJobs(${pagination.current_page - 1})" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">Previous</button>`;
            }
            
            // Page numbers
            for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
                const activeClass = i === pagination.current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50';
                paginationHTML += `<button onclick="loadStuckJobs(${i})" class="px-3 py-2 text-sm border border-gray-300 ${activeClass}">${i}</button>`;
            }
            
            // Next button
            if (pagination.current_page < pagination.last_page) {
                paginationHTML += `<button onclick="loadStuckJobs(${pagination.current_page + 1})" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">Next</button>`;
            }
            
            controls.innerHTML = paginationHTML;
        }

        // Stuck job control functions
        async function resetStuckJob(id, type = 'queue_job') {
            showLoading();
            try {
                const response = await fetch(`/queue-dashboard/api/reset-stuck-job/${id}?type=${type}`, {
                    method: 'POST',
                    headers: window.axios.defaults.headers
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadStuckJobs(currentStuckJobsPage);
                    await loadDashboard(); // Refresh counts
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error resetting stuck job', 'error');
            } finally {
                hideLoading();
            }
        }

        async function deleteStuckJob(id, type = 'queue_job') {
            const jobTypeText = type === 'progress_log' ? 'progress log job' : 'queue job';
            if (!confirm(`Are you sure you want to delete this stuck ${jobTypeText} permanently?`)) return;
            
            showLoading();
            try {
                const response = await fetch(`/queue-dashboard/api/delete-stuck-job/${id}?type=${type}`, {
                    method: 'DELETE',
                    headers: window.axios.defaults.headers
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadStuckJobs(currentStuckJobsPage);
                    await loadDashboard(); // Refresh counts
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error deleting stuck job', 'error');
            } finally {
                hideLoading();
            }
        }

        async function resetAllStuckJobs() {
            const hoursFilter = document.getElementById('stuck-hours-filter').value;
            if (!confirm(`Are you sure you want to reset all jobs stuck for more than ${hoursFilter} hours? This will make them available for processing again.`)) return;
            
            showLoading();
            try {
                const response = await fetch(`/queue-dashboard/api/reset-all-stuck?hours=${hoursFilter}`, {
                    method: 'POST',
                    headers: window.axios.defaults.headers
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadStuckJobs(1);
                    await loadDashboard(); // Refresh counts
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error resetting all stuck jobs', 'error');
            } finally {
                hideLoading();
            }
        }

        async function clearAllStuckJobs() {
            const hoursFilter = document.getElementById('stuck-hours-filter').value;
            if (!confirm(`Are you sure you want to permanently delete all jobs stuck for more than ${hoursFilter} hours? This action cannot be undone.`)) return;
            
            showLoading();
            try {
                const response = await fetch(`/queue-dashboard/api/clear-all-stuck?hours=${hoursFilter}`, {
                    method: 'POST',
                    headers: window.axios.defaults.headers
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    await loadStuckJobs(1);
                    await loadDashboard(); // Refresh counts
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error clearing all stuck jobs', 'error');
            } finally {
                hideLoading();
            }
        }

        // Utility functions
        function showLoading() {
            document.getElementById('loading-overlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loading-overlay').classList.add('hidden');
        }

        // API Error Handling for Rate Limiting
        async function handleApiError(response, defaultMessage = 'API request failed') {
            if (response.status === 429) {
                // Rate limit exceeded
                try {
                    const errorData = await response.json();
                    const retryAfter = response.headers.get('Retry-After') || errorData.retry_after || 60;
                    const limit = response.headers.get('X-RateLimit-Limit') || errorData.limit || 'unknown';
                    const window = response.headers.get('X-RateLimit-Window') || errorData.window || 'unknown';
                    
                    showRateLimitError(errorData.message || 'Rate limit exceeded', retryAfter, limit, window);
                    
                    // Auto-refresh has been disabled - manual refresh only
                    
                } catch (e) {
                    showToast('Rate limit exceeded. Please wait before making more requests.', 'error');
                }
            } else {
                // Other API errors
                try {
                    const errorData = await response.json();
                    showToast(errorData.message || defaultMessage, 'error');
                } catch (e) {
                    showToast(defaultMessage, 'error');
                }
            }
        }

        function showRateLimitError(message, retryAfter, limit, window) {
            const rateLimitToast = `
                <div class="rate-limit-warning bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400 text-xl"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-yellow-800">Rate Limit Exceeded</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>${message}</p>
                                <div class="mt-2 text-xs">
                                    <p><strong>Limit:</strong> ${limit} requests per ${window}</p>
                                    <p><strong>Retry after:</strong> ${retryAfter} seconds</p>
                                    <p><strong>Status:</strong> Auto-refresh temporarily disabled</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="bg-yellow-200 rounded-full h-2">
                                    <div class="bg-yellow-600 h-2 rounded-full transition-all duration-1000" 
                                         style="width: 0%" id="rate-limit-countdown"></div>
                                </div>
                                <p class="text-xs text-yellow-600 mt-1">
                                    <span id="countdown-text">${retryAfter}s remaining</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert rate limit warning at the top of the page
            const container = document.querySelector('.max-w-7xl');
            const warningDiv = document.createElement('div');
            warningDiv.innerHTML = rateLimitToast;
            warningDiv.className = 'rate-limit-container';
            container.insertBefore(warningDiv, container.firstChild);
            
            // Start countdown
            startRateLimitCountdown(retryAfter);
        }

        function startRateLimitCountdown(seconds) {
            let remaining = seconds;
            const progressBar = document.getElementById('rate-limit-countdown');
            const countdownText = document.getElementById('countdown-text');
            
            if (!progressBar || !countdownText) return;
            
            const interval = setInterval(() => {
                remaining--;
                const progress = ((seconds - remaining) / seconds) * 100;
                progressBar.style.width = `${progress}%`;
                countdownText.textContent = `${remaining}s remaining`;
                
                if (remaining <= 0) {
                    clearInterval(interval);
                    // Remove the warning
                    const warningContainer = document.querySelector('.rate-limit-container');
                    if (warningContainer) {
                        warningContainer.remove();
                    }
                }
            }, 1000);
        }

        // Enhanced API request wrapper with rate limit handling
        async function makeApiRequest(url, options = {}) {
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...options.headers
                    }
                });
                
                if (!response.ok) {
                    await handleApiError(response, `Request failed: ${url}`);
                    return null;
                }
                
                return response;
            } catch (error) {
                console.error('API request error:', error);
                showToast('Network error occurred', 'error');
                return null;
            }
        }

        // Performance Analytics Functions
        async function loadPerformanceAnalytics() {
            try {
                const days = document.getElementById('analytics-timeframe').value;
                const response = await fetch(`/queue-dashboard/api/performance-analytics?days=${days}`);
                const data = await response.json();
                
                updateQueuePerformanceTable(data.queue_stats);
                updateJobClassPerformanceTable(data.job_class_stats);
                updateDailyTrendsChart(data.daily_stats);
                
            } catch (error) {
                console.error('Error loading performance analytics:', error);
                showToast('Error loading performance analytics', 'error');
            }
        }

        function updateQueuePerformanceTable(queueStats) {
            const tbody = document.getElementById('queue-performance-table');
            tbody.innerHTML = '';

            queueStats.forEach(queue => {
                const successRate = queue.total_jobs > 0 
                    ? ((queue.completed_jobs / queue.total_jobs) * 100).toFixed(1)
                    : '0.0';
                
                const avgTime = queue.avg_processing_time_ms 
                    ? (queue.avg_processing_time_ms / 1000).toFixed(2) + 's'
                    : 'N/A';

                const row = `
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-medium text-gray-900">${queue.queue}</td>
                        <td class="py-2 text-right text-gray-700">${queue.total_jobs.toLocaleString()}</td>
                        <td class="py-2 text-right">
                            <span class="px-2 py-1 text-xs rounded-full ${getSuccessRateClass(successRate)}">
                                ${successRate}%
                            </span>
                        </td>
                        <td class="py-2 text-right text-gray-700">${avgTime}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });

            if (queueStats.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-500">No queue data available</td></tr>';
            }
        }

        function updateJobClassPerformanceTable(jobClassStats) {
            const tbody = document.getElementById('job-class-performance-table');
            tbody.innerHTML = '';

            jobClassStats.forEach(jobClass => {
                const successRate = jobClass.total_jobs > 0 
                    ? ((jobClass.completed_jobs / jobClass.total_jobs) * 100).toFixed(1)
                    : '0.0';
                
                const avgTime = jobClass.avg_processing_time_ms 
                    ? (jobClass.avg_processing_time_ms / 1000).toFixed(2) + 's'
                    : 'N/A';

                const shortClassName = jobClass.job_class.length > 30 
                    ? jobClass.job_class.substring(jobClass.job_class.lastIndexOf('\\') + 1)
                    : jobClass.job_class;

                const row = `
                    <tr class="border-b border-gray-100">
                        <td class="py-2 font-medium text-gray-900" title="${jobClass.job_class}">${shortClassName}</td>
                        <td class="py-2 text-right text-gray-700">${jobClass.total_jobs.toLocaleString()}</td>
                        <td class="py-2 text-right">
                            <span class="px-2 py-1 text-xs rounded-full ${getSuccessRateClass(successRate)}">
                                ${successRate}%
                            </span>
                        </td>
                        <td class="py-2 text-right text-gray-700">${avgTime}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });

            if (jobClassStats.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-500">No job class data available</td></tr>';
            }
        }

        function getSuccessRateClass(rate) {
            const numRate = parseFloat(rate);
            if (numRate >= 95) return 'bg-green-100 text-green-800';
            if (numRate >= 80) return 'bg-yellow-100 text-yellow-800';
            return 'bg-red-100 text-red-800';
        }

        function updateDailyTrendsChart(dailyStats) {
            const ctx = document.getElementById('daily-trends-chart').getContext('2d');
            
            if (dailyTrendsChart) {
                dailyTrendsChart.destroy();
            }

            const labels = dailyStats.map(stat => {
                const date = new Date(stat.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });

            const completedData = dailyStats.map(stat => stat.completed_jobs || 0);
            const failedData = dailyStats.map(stat => stat.failed_jobs || 0);
            const avgTimeData = dailyStats.map(stat => 
                stat.avg_processing_time_ms ? (stat.avg_processing_time_ms / 1000).toFixed(2) : 0
            );

            dailyTrendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Completed Jobs',
                            data: completedData,
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Failed Jobs',
                            data: failedData,
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Avg Processing Time (s)',
                            data: avgTimeData,
                            borderColor: 'rgb(168, 85, 247)',
                            backgroundColor: 'rgba(168, 85, 247, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Job Count'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Processing Time (seconds)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    }
                }
            });
        }

        function updateChartTimeframe() {
            loadPerformanceHistory();
        }

        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            const toastId = 'toast-' + Date.now();
            
            const toastColors = {
                success: 'bg-green-600',
                error: 'bg-red-600',
                warning: 'bg-yellow-600',
                info: 'bg-blue-600'
            };
            
            const toastIcons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-triangle',
                warning: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `${toastColors[type]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 transform translate-x-full transition-transform duration-300`;
            toast.innerHTML = `
                <i class="fas ${toastIcons[type]}"></i>
                <span>${message}</span>
                <button onclick="closeToast('${toastId}')" class="ml-auto">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                closeToast(toastId);
            }, 5000);
        }

        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // Cron Jobs Functions
        async function loadCronJobs() {
            try {
                const response = await makeApiRequest('/queue-dashboard/api/cron-jobs');
                if (response && response.ok) {
                    const data = await response.json();
                    updateCronJobsSummary(data.summary);
                    updateCronJobsTable(data.cron_jobs);
                }
            } catch (error) {
                console.error('Error loading cron jobs:', error);
                showToast('Error loading cron jobs', 'error');
            }
        }

        function updateCronJobsSummary(summary) {
            document.getElementById('cron-total-jobs').textContent = summary.total_jobs || 0;
            document.getElementById('cron-protected-jobs').textContent = summary.jobs_with_overlapping_protection || 0;
            document.getElementById('cron-background-jobs').textContent = summary.background_jobs || 0;
            
            if (summary.next_job) {
                document.getElementById('cron-next-job').textContent = summary.next_job.command;
                const nextTime = new Date(summary.next_job.next_run).toLocaleString();
                document.getElementById('cron-next-time').textContent = nextTime;
            } else {
                document.getElementById('cron-next-job').textContent = 'None scheduled';
                document.getElementById('cron-next-time').textContent = 'No upcoming jobs';
            }
        }

        function updateCronJobsTable(cronJobs) {
            const container = document.getElementById('cron-jobs-table');
            container.innerHTML = '';
            
            if (!cronJobs || cronJobs.length === 0) {
                container.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-clock text-2xl mb-2"></i>
                            <p>No scheduled cron jobs found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            cronJobs.forEach(job => {
                const row = createCronJobRow(job);
                container.innerHTML += row;
            });
        }

        function createCronJobRow(job) {
            const features = [];
            
            if (job.has_conditions) {
                features.push('<span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Conditional</span>');
            }
            
            if (job.overlapping_protection) {
                features.push('<span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Protected</span>');
            }
            
            if (job.runs_in_background) {
                features.push('<span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">Background</span>');
            }
            
            if (job.environment_specific) {
                features.push('<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Env-Specific</span>');
            }

            const nextRun = job.next_run && job.next_run !== 'Invalid cron expression' 
                ? new Date(job.next_run).toLocaleString()
                : job.next_run || 'Unknown';

            const nextRunClass = job.next_run === 'Invalid cron expression' 
                ? 'text-red-600' 
                : 'text-gray-900';

            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col">
                            <div class="text-sm font-medium text-gray-900">${escapeHtml(job.command)}</div>
                            <div class="text-xs text-gray-500 font-mono">${escapeHtml(job.cron_expression || '')}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-900">${escapeHtml(job.frequency)}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm ${nextRunClass}">${escapeHtml(nextRun)}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1">
                            ${features.join(' ')}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-sm text-gray-600">${escapeHtml(job.description || 'No description')}</span>
                    </td>
                </tr>
            `;
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Failed Job Details Modal Functions
        async function viewJobDetails(jobUuid) {
            try {
                showLoading();
                const response = await fetch(`/queue-dashboard/api/failed-job-details/${jobUuid}`);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to fetch job details');
                }
                
                showJobDetailsModal(data);
            } catch (error) {
                console.error('Error fetching job details:', error);
                showToast('Error fetching job details: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }

        function showJobDetailsModal(jobData) {
            const modal = document.getElementById('job-details-modal');
            const content = document.getElementById('job-details-content');
            
            // Build the modal content
            content.innerHTML = `
                <!-- Basic Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Job Class</label>
                            <div class="mt-1 text-sm text-gray-900">${escapeHtml(jobData.basic_info.job_class)}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Queue</label>
                            <div class="mt-1 text-sm text-gray-900">${escapeHtml(jobData.basic_info.queue)}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Connection</label>
                            <div class="mt-1 text-sm text-gray-900">${escapeHtml(jobData.basic_info.connection)}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Failed At</label>
                            <div class="mt-1 text-sm text-gray-900">${escapeHtml(jobData.basic_info.failed_at)} (${escapeHtml(jobData.basic_info.failed_at_human)})</div>
                        </div>
                    </div>
                </div>

                ${jobData.has_detailed_info ? `
                <!-- Error Information -->
                <div class="bg-red-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-red-900 mb-3">Error Information</h4>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 text-xs rounded-full ${getErrorTypeBadgeColor(jobData.detailed_info.error_type)}">${escapeHtml(jobData.detailed_info.error_type || 'Unknown')}</span>
                            <span class="px-2 py-1 text-xs rounded-full ${getErrorCategoryBadgeColor(jobData.detailed_info.error_category)}">${escapeHtml(jobData.detailed_info.error_category || 'Unknown')}</span>
                            <span class="px-2 py-1 text-xs rounded-full ${jobData.detailed_info.is_retryable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${jobData.detailed_info.is_retryable ? 'Retryable' : 'Non-Retryable'}</span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Failure Reason</label>
                            <div class="mt-1 text-sm text-gray-900 bg-white p-3 rounded border">${escapeHtml(jobData.detailed_info.failure_reason || 'No failure reason available')}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Suggested Resolution</label>
                            <div class="mt-1 text-sm text-gray-900 bg-white p-3 rounded border">${escapeHtml(jobData.detailed_info.suggested_resolution || 'No suggestions available')}</div>
                        </div>
                    </div>
                </div>
                ` : `
                <!-- Basic Error Information (for older jobs) -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-yellow-900 mb-3">Error Information</h4>
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">${escapeHtml(jobData.message || 'Enhanced details not available for this job.')}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Exception</label>
                        <div class="mt-1 text-sm text-gray-900 bg-white p-3 rounded border max-h-40 overflow-y-auto">${escapeHtml(jobData.basic_info.exception || 'No exception details available')}</div>
                    </div>
                </div>
                `}

                ${jobData.has_detailed_info ? `
                <!-- Execution Details -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-blue-900 mb-3">Execution Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Attempts</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.detailed_info.attempts || 'Unknown'} / ${jobData.detailed_info.max_tries || 'Unlimited'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Timeout</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.detailed_info.timeout ? jobData.detailed_info.timeout + 's' : 'No timeout'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Memory Usage</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.memory_usage || 'Unknown'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Peak Memory</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.peak_memory || 'Unknown'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Execution Time</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.execution_time || 'Unknown'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Worker PID</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.worker_pid || 'Unknown'}</div>
                        </div>
                    </div>
                </div>

                <!-- Stack Trace -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Stack Trace</h4>
                    <div class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm max-h-96 overflow-y-auto">
                        <pre>${escapeHtml(jobData.detailed_info.stack_trace || 'No stack trace available')}</pre>
                    </div>
                </div>

                <!-- System Information -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-yellow-900 mb-3">System Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">PHP Version</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.php_version || 'Unknown'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Server Software</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.server_info?.server_software || 'Unknown'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Operating System</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.server_info?.os || 'Unknown'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Memory Limit</label>
                            <div class="mt-1 text-sm text-gray-900">${jobData.system_info.server_info?.memory_limit || 'Unknown'}</div>
                        </div>
                    </div>
                </div>

                <!-- Payload Data -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-purple-900 mb-3">Payload Data</h4>
                    <div class="bg-white p-4 rounded border max-h-96 overflow-y-auto">
                        <pre class="text-sm text-gray-700">${escapeHtml(JSON.stringify(jobData.payload_data || jobData.basic_info.payload, null, 2))}</pre>
                    </div>
                </div>

                ${jobData.resolution_notes ? `
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-green-900 mb-3">Resolution Notes</h4>
                        <div class="text-sm text-gray-900">${escapeHtml(jobData.resolution_notes)}</div>
                    </div>
                ` : ''}
                ` : ''}
            `;
            
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeJobDetailsModal() {
            const modal = document.getElementById('job-details-modal');
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function getErrorTypeBadgeColor(errorType) {
            const colors = {
                'database': 'bg-red-100 text-red-800',
                'timeout': 'bg-yellow-100 text-yellow-800',
                'memory': 'bg-purple-100 text-purple-800',
                'network': 'bg-blue-100 text-blue-800',
                'authentication': 'bg-orange-100 text-orange-800',
                'validation': 'bg-pink-100 text-pink-800',
                'external_api': 'bg-indigo-100 text-indigo-800',
                'file_system': 'bg-green-100 text-green-800',
            };
            return colors[errorType] || 'bg-gray-100 text-gray-800';
        }

        function getErrorCategoryBadgeColor(errorCategory) {
            const colors = {
                'recoverable': 'bg-green-100 text-green-800',
                'permanent': 'bg-red-100 text-red-800',
                'configuration': 'bg-yellow-100 text-yellow-800',
                'resource': 'bg-purple-100 text-purple-800',
                'business_logic': 'bg-blue-100 text-blue-800',
            };
            return colors[errorCategory] || 'bg-gray-100 text-gray-800';
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('job-details-modal');
            if (event.target === modal) {
                closeJobDetailsModal();
            }
        });
    </script>

    <!-- Failed Job Details Modal -->
    <div id="job-details-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg max-w-6xl w-full max-h-[90vh] overflow-y-auto mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Failed Job Details</h3>
                <button onclick="closeJobDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="px-6 py-4">
                <div id="job-details-content" class="space-y-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</body>
</html> 