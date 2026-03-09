<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Performance Monitoring Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js (use cdnjs to avoid missing sourcemap on jsDelivr) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Custom CSS -->
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .metric-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        .status-healthy { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-critical { color: #dc3545; }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        .endpoint-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .endpoint-item {
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
        }
        .endpoint-item:hover {
            background-color: #f8f9fa;
            border-left-color: #007bff;
        }
        .auto-refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .time-range-selector {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <!-- Auto-refresh indicator -->
    <div class="auto-refresh-indicator">
        <div class="bg-primary text-white px-3 py-2 rounded-pill d-flex align-items-center" id="refreshIndicator">
            <i class="fas fa-sync-alt me-2" id="refreshIcon"></i>
            <span id="refreshText">Auto-refresh: ON</span>
        </div>
    </div>

    <!-- Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-chart-line me-3"></i>
                        API Performance Monitoring
                    </h1>
                    <p class="mb-0 opacity-75">Real-time monitoring and analytics for {{ number_format(1600) }}+ APIs</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-outline-light" onclick="toggleAutoRefresh()">
                            <i class="fas fa-sync-alt me-1"></i> Toggle Refresh
                        </button>
                        <button class="btn btn-outline-light" onclick="exportData()">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Time Range Selector -->
        <div class="time-range-selector">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Time Range
                    </h5>
                </div>
                <div class="col-md-6">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-range="1h">1 Hour</button>
                        <button type="button" class="btn btn-outline-primary" data-range="6h">6 Hours</button>
                        <button type="button" class="btn btn-outline-primary" data-range="24h">24 Hours</button>
                        <button type="button" class="btn btn-outline-primary" data-range="7d">7 Days</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overview Metrics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-6 text-primary mb-2">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <h4 class="card-title" id="totalRequests">--</h4>
                        <p class="text-muted mb-0">Total Requests</p>
                        <small class="text-muted" id="requestsRate">-- req/min</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-6 text-info mb-2">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                        <h4 class="card-title" id="avgResponseTime">--</h4>
                        <p class="text-muted mb-0">Avg Response Time</p>
                        <small class="text-muted">milliseconds</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-6 mb-2" id="errorIcon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 class="card-title" id="errorRate">--%</h4>
                        <p class="text-muted mb-0">Error Rate</p>
                        <small class="text-muted" id="errorCount">-- errors</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card metric-card h-100">
                    <div class="card-body text-center">
                        <div class="display-6 text-success mb-2">
                            <i class="fas fa-server"></i>
                        </div>
                        <h4 class="card-title" id="activeEndpoints">--</h4>
                        <p class="text-muted mb-0">Active Endpoints</p>
                        <small class="text-muted">last hour</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Response Time Trends
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="responseTimeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Request Volume
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="requestVolumeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Endpoints and Slow Endpoints -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-fire me-2"></i>
                            Top Endpoints by Traffic
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" data-sort="requests">Requests</button>
                            <button class="btn btn-outline-secondary" data-sort="response_time">Response Time</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="endpoint-list" id="topEndpointsList">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading endpoints...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                            Slow Endpoints
                        </h5>
                        <small class="text-muted">Response time > 1000ms</small>
                    </div>
                    <div class="card-body">
                        <div class="endpoint-list" id="slowEndpointsList">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading slow endpoints...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Errors & Crashes -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bomb me-2 text-danger"></i>
                            Critical Errors & Crashes
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-danger" id="totalErrors">0</span>
                            <span class="badge bg-info" id="httpErrors">0 HTTP</span>
                            <span class="badge bg-warning" id="laravelErrors">0 Laravel</span>
                            <span class="badge bg-secondary" id="apacheErrors">0 Apache</span>
                            <span class="badge bg-dark" id="systemErrors">0 System</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6>Recent Errors (Last 20)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Source</th>
                                                <th>Level</th>
                                                <th>Message</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recentErrorsList">
                                            <tr>
                                                <td colspan="4" class="text-muted text-center">No recent errors</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6>Error Sources</h6>
                                <canvas id="errorSourceChart" width="200" height="200"></canvas>
                                <div class="mt-3" id="errorSourceDetails"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-heartbeat me-2"></i>
                            System Health
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="systemHealthMetrics">
                            <div class="col-md-3 text-center">
                                <div class="display-6 text-muted mb-2">
                                    <i class="fas fa-database"></i>
                                </div>
                                <p class="mb-0">Database</p>
                                <span class="badge bg-secondary">Checking...</span>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="display-6 text-muted mb-2">
                                    <i class="fas fa-memory"></i>
                                </div>
                                <p class="mb-0">Memory Usage</p>
                                <span class="badge bg-secondary">Checking...</span>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="display-6 text-muted mb-2">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <p class="mb-0">Cache Status</p>
                                <span class="badge bg-secondary">Checking...</span>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="display-6 text-muted mb-2">
                                    <i class="fas fa-hdd"></i>
                                </div>
                                <p class="mb-0">Disk Space</p>
                                <span class="badge bg-secondary">Checking...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        let autoRefreshEnabled = true;
        let refreshInterval;
        let currentTimeRange = '1h';
        let responseTimeChart, requestVolumeChart;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            loadDashboardData();
            startAutoRefresh();
            
            // Time range selector
            document.querySelectorAll('[data-range]').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('[data-range]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentTimeRange = this.dataset.range;
                    loadDashboardData();
                });
            });
            
            // Add click handlers for expandable error messages
            document.addEventListener('click', function(e) {
                // Handle expand link clicks
                if (e.target.classList.contains('expand-link') || e.target.closest('.expand-link')) {
                    e.preventDefault();
                    const errorMessage = e.target.closest('.error-message');
                    const errorText = errorMessage.querySelector('.error-text');
                    const expandLink = errorMessage.querySelector('.expand-link');
                    const fullMessage = errorText.getAttribute('data-full-message');
                    
                    if (errorText.classList.contains('truncated')) {
                        // Expand
                        errorText.innerHTML = fullMessage;
                        errorText.classList.remove('truncated');
                        expandLink.innerHTML = '<i class="fas fa-compress-alt"></i> Click to collapse';
                    } else {
                        // Collapse
                        errorText.innerHTML = fullMessage.substring(0, 80) + '...';
                        errorText.classList.add('truncated');
                        expandLink.innerHTML = '<i class="fas fa-expand-alt"></i> Click to expand full message';
                    }
                }
                
                // Handle click on truncated error text
                if (e.target.classList.contains('error-text') && e.target.classList.contains('truncated')) {
                    const fullMessage = e.target.getAttribute('data-full-message');
                    const expandLink = e.target.closest('.error-message').querySelector('.expand-link');
                    
                    e.target.innerHTML = fullMessage;
                    e.target.classList.remove('truncated');
                    if (expandLink) {
                        expandLink.innerHTML = '<i class="fas fa-compress-alt"></i> Click to collapse';
                    }
                }
            });
        });

        // Initialize charts
        function initializeCharts() {
            const ctx1 = document.getElementById('responseTimeChart').getContext('2d');
            responseTimeChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Avg Response Time (ms)',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grace: '10%',
                            title: {
                                display: true,
                                text: 'Response Time (ms)'
                            }
                        }
                    }
                }
            });

            const ctx2 = document.getElementById('requestVolumeChart').getContext('2d');
            requestVolumeChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Requests',
                        data: [],
                        backgroundColor: '#28a745',
                        borderColor: '#20c997',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grace: '10%',
                            title: {
                                display: true,
                                text: 'Number of Requests'
                            }
                        }
                    }
                }
            });
        }

        // Load dashboard data
        async function loadDashboardData() {
            try {
                const response = await fetch(`/api-performance/api/stats?range=${currentTimeRange}`);
                const data = await response.json();

                updateOverviewMetrics(data.overview);
                updateCharts(data);
                await loadTopEndpoints();
                await loadSlowEndpoints();
                await loadCriticalErrors();
                await loadSystemHealth();
                
            } catch (error) {
                console.error('Failed to load dashboard data:', error);
                showAlert('Error loading dashboard data', 'danger');
            }
        }

        // Update overview metrics
        function updateOverviewMetrics(overview) {
            document.getElementById('totalRequests').textContent = formatNumber(overview.total_requests);
            document.getElementById('requestsRate').textContent = `${overview.requests_per_minute} req/min`;
            document.getElementById('avgResponseTime').textContent = `${overview.avg_response_time_ms}ms`;
            
            const errorRate = overview.error_rate_percent;
            document.getElementById('errorRate').textContent = `${errorRate}%`;
            document.getElementById('errorCount').textContent = `${overview.error_count} errors`;
            
            // Update error rate styling
            const errorIcon = document.getElementById('errorIcon');
            errorIcon.className = 'display-6 mb-2 ' + getStatusClass(errorRate, 5, 10);
            
            document.getElementById('activeEndpoints').textContent = formatNumber(overview.active_endpoints || 0);
        }

        // Load top endpoints
        async function loadTopEndpoints() {
            try {
                const response = await fetch(`/api-performance/api/top-endpoints?range=${currentTimeRange}&sort=requests`);
                const data = await response.json();
                
                const container = document.getElementById('topEndpointsList');
                container.innerHTML = '';
                
                data.endpoints.slice(0, 10).forEach(endpoint => {
                    const item = createEndpointItem(endpoint, 'top');
                    container.appendChild(item);
                });
                
                if (data.endpoints.length === 0) {
                    container.innerHTML = '<p class="text-muted text-center">No data available</p>';
                }
            } catch (error) {
                console.error('Failed to load top endpoints:', error);
            }
        }

        // Load slow endpoints
        async function loadSlowEndpoints() {
            try {
                const response = await fetch(`/api-performance/api/slow-endpoints?range=${currentTimeRange}`);
                const data = await response.json();
                
                const container = document.getElementById('slowEndpointsList');
                container.innerHTML = '';
                
                data.slow_endpoints.slice(0, 10).forEach(endpoint => {
                    const item = createEndpointItem(endpoint, 'slow');
                    container.appendChild(item);
                });
                
                if (data.slow_endpoints.length === 0) {
                    container.innerHTML = '<p class="text-success text-center"><i class="fas fa-check-circle me-2"></i>No slow endpoints detected</p>';
                }
            } catch (error) {
                console.error('Failed to load slow endpoints:', error);
            }
        }

        // Load critical errors data
        async function loadCriticalErrors() {
            try {
                const response = await fetch(`/api-performance/api/critical-errors?range=${currentTimeRange}`);
                const data = await response.json();
                
                updateCriticalErrorsDisplay(data);
                
            } catch (error) {
                console.error('Failed to load critical errors:', error);
                document.getElementById('totalErrors').textContent = 'Error';
                document.getElementById('httpErrors').textContent = 'Error';
                document.getElementById('laravelErrors').textContent = 'Error';
                document.getElementById('apacheErrors').textContent = 'Error';
                document.getElementById('systemErrors').textContent = 'Error';
                document.getElementById('recentErrorsList').innerHTML = '<tr><td colspan="4" class="text-danger text-center">Failed to load errors</td></tr>';
            }
        }

        // Update critical errors display
        function updateCriticalErrorsDisplay(data) {
            // Update badges
            document.getElementById('totalErrors').textContent = data.total_errors;
            document.getElementById('httpErrors').textContent = `${data.http_errors} HTTP`;
            document.getElementById('laravelErrors').textContent = `${data.laravel_errors} Laravel`;
            document.getElementById('apacheErrors').textContent = `${data.apache_errors} Apache`;
            document.getElementById('systemErrors').textContent = `${data.system_errors} System`;

            // Update recent errors table
            const errorsList = document.getElementById('recentErrorsList');
            errorsList.innerHTML = '';
            
            if (data.recent_errors && data.recent_errors.length > 0) {
                data.recent_errors.forEach(error => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <span class="badge ${getSourceBadgeClass(error.source)}">${error.source}</span>
                        </td>
                        <td>
                            <span class="badge ${getLevelBadgeClass(error.level)}">${error.level.toUpperCase()}</span>
                        </td>
                        <td>
                            <div class="error-message" style="max-width: 500px; word-wrap: break-word;">
                                <span class="error-text ${error.message.length > 80 ? 'truncated' : ''}" 
                                      data-full-message="${error.message.replace(/"/g, '&quot;')}"
                                      style="cursor: ${error.message.length > 80 ? 'pointer' : 'default'};">
                                    ${error.message.length > 80 ? error.message.substring(0, 80) + '...' : error.message}
                                </span>
                                ${error.message.length > 80 ? '<br><small class="text-primary expand-link" style="cursor: pointer;"><i class="fas fa-expand-alt"></i> Click to expand full message</small>' : ''}
                            </div>
                        </td>
                        <td>
                            <small class="text-muted">${formatTimestamp(error.timestamp)}</small>
                        </td>
                    `;
                    errorsList.appendChild(tr);
                });
            } else {
                errorsList.innerHTML = '<tr><td colspan="4" class="text-muted text-center">No recent errors</td></tr>';
            }

            // Update error source chart
            updateErrorSourceChart(data.error_breakdown || {});
        }

        // Get badge class for error source
        function getSourceBadgeClass(source) {
            const classes = {
                'HTTP': 'bg-info',
                'Laravel': 'bg-warning',
                'Apache': 'bg-secondary',
                'System': 'bg-dark'
            };
            return classes[source] || 'bg-light text-dark';
        }

        // Get badge class for error level
        function getLevelBadgeClass(level) {
            const classes = {
                'emergency': 'bg-danger',
                'critical': 'bg-danger',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'notice': 'bg-info',
                'info': 'bg-info'
            };
            return classes[level] || 'bg-secondary';
        }

        // Format timestamp for display - shows exact time when error occurred
        function formatTimestamp(timestamp) {
            // Backend returns UTC timestamps in 'Y-m-d H:i:s' format
            // Add 'Z' to indicate UTC timezone for proper parsing
            const utcTimestamp = timestamp.includes('Z') ? timestamp : timestamp.replace(' ', 'T') + 'Z';
            const date = new Date(utcTimestamp);
            const now = new Date();
            const diffSeconds = Math.floor((now - date) / 1000);
            const diffMinutes = Math.floor(diffSeconds / 60);
            const diffHours = Math.floor(diffMinutes / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            // Format: "2h 15m ago (14:23:45)" for recent errors
            // Format: "Nov 17, 23:24:53" for older errors
            
            const timeStr = date.toLocaleTimeString('en-US', { 
                hour12: false, 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            
            if (diffSeconds < 60) {
                return `Just now (${timeStr})`;
            } else if (diffMinutes < 60) {
                return `${diffMinutes}m ago (${timeStr})`;
            } else if (diffHours < 24) {
                const mins = diffMinutes % 60;
                return `${diffHours}h ${mins}m ago (${timeStr})`;
            } else {
                const dateStr = date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric'
                });
                return `${dateStr}, ${timeStr}`;
            }
        }

        // Update error source chart
        function updateErrorSourceChart(errorBreakdown) {
            const canvas = document.getElementById('errorSourceChart');
            const ctx = canvas.getContext('2d');
            
            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            const sources = Object.keys(errorBreakdown);
            if (sources.length === 0) {
                ctx.fillStyle = '#6c757d';
                ctx.font = '14px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No errors', canvas.width / 2, canvas.height / 2);
                return;
            }
            
            const total = Object.values(errorBreakdown).reduce((sum, count) => sum + count, 0);
            const colors = ['#17a2b8', '#ffc107', '#6c757d', '#343a40', '#dc3545'];
            
            let startAngle = 0;
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = Math.min(centerX, centerY) - 10;
            
            sources.forEach((source, index) => {
                const count = errorBreakdown[source];
                const angle = (count / total) * 2 * Math.PI;
                
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, startAngle, startAngle + angle);
                ctx.lineTo(centerX, centerY);
                ctx.fillStyle = colors[index % colors.length];
                ctx.fill();
                
                startAngle += angle;
            });
            
            // Update details
            const detailsDiv = document.getElementById('errorSourceDetails');
            detailsDiv.innerHTML = '';
            sources.forEach((source, index) => {
                const count = errorBreakdown[source];
                const percentage = ((count / total) * 100).toFixed(1);
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center mb-1';
                div.innerHTML = `
                    <div>
                        <span class="badge ${getSourceBadgeClass(source)}">${source}</span>
                    </div>
                    <div>
                        <span class="fw-bold">${count}</span>
                        <small class="text-muted">(${percentage}%)</small>
                    </div>
                `;
                detailsDiv.appendChild(div);
            });
        }

        // Load system health data
        async function loadSystemHealth() {
            try {
                const response = await fetch('/api-performance/api/system-health');
                const data = await response.json();
                updateSystemHealth(data);
            } catch (error) {
                console.error('Failed to load system health:', error);
                // Show error state for all health metrics
                const healthContainer = document.getElementById('systemHealthMetrics');
                const badges = healthContainer.querySelectorAll('.badge');
                badges.forEach(badge => {
                    badge.textContent = 'Error';
                    badge.className = 'badge bg-danger';
                });
            }
        }

        // Update system health UI
        function updateSystemHealth(healthData) {
            const healthContainer = document.getElementById('systemHealthMetrics');
            const metrics = healthContainer.children;

            // Database / Metrics storage status (first metric)
            // Support both legacy `database` key and new `metrics_storage` structure
            const dbBadge = metrics[0].querySelector('.badge');
            const dbSource = healthData.metrics_storage || healthData.database || {};
            const dbStatus = dbSource.status || 'unknown';

            if (dbStatus === 'healthy') {
                dbBadge.textContent = '✓ Healthy';
                dbBadge.className = 'badge bg-success';
            } else if (dbStatus === 'warning') {
                dbBadge.textContent = '⚠ Warning';
                dbBadge.className = 'badge bg-warning';
            } else {
                dbBadge.textContent = '✗ Error';
                dbBadge.className = 'badge bg-danger';
            }

            // Memory usage (second metric) 
            const memBadge = metrics[1].querySelector('.badge');
            const memory = healthData.memory_usage || {};
            if (memory.status && memory.status !== 'error') {
                const usedPercentage = typeof memory.used_percentage === 'number'
                    ? memory.used_percentage
                    : 0;
                memBadge.textContent = `${usedPercentage}%`;
                memBadge.className = `badge ${getStatusBadgeClass(memory.status)}`;
            } else {
                memBadge.textContent = 'Error';
                memBadge.className = 'badge bg-danger';
            }

            // Cache status (third metric)
            const cpuBadge = metrics[2].querySelector('.badge');
            const cacheStatus = (healthData.cache && healthData.cache.status) || 'error';
            if (cacheStatus === 'healthy') {
                cpuBadge.textContent = '✓ Healthy';
                cpuBadge.className = 'badge bg-success';
            } else if (cacheStatus === 'warning') {
                cpuBadge.textContent = '⚠ Warning';
                cpuBadge.className = 'badge bg-warning';
            } else {
                cpuBadge.textContent = '✗ Error';
                cpuBadge.className = 'badge bg-danger';
            }

            // Disk space (fourth metric)
            const diskBadge = metrics[3].querySelector('.badge');
            const disk = healthData.disk_space || {};
            if (disk.status && disk.status !== 'error') {
                const usedPercentage = typeof disk.used_percentage === 'number'
                    ? disk.used_percentage
                    : 0;
                diskBadge.textContent = `${usedPercentage}% used`;
                diskBadge.className = `badge ${getStatusBadgeClass(disk.status)}`;
            } else {
                diskBadge.textContent = 'Error';
                diskBadge.className = 'badge bg-danger';
            }
        }

        // Get badge class based on health status
        function getStatusBadgeClass(status) {
            switch (status) {
                case 'healthy': return 'bg-success';
                case 'warning': return 'bg-warning';
                case 'critical': return 'bg-danger';
                default: return 'bg-danger';
            }
        }

        // Create endpoint item HTML
        function createEndpointItem(endpoint, type) {
            const div = document.createElement('div');
            div.className = 'endpoint-item p-3 border-bottom';
            
            // Backend returns different field names for different endpoints
            // Top endpoints: avg_response_time, requests
            // Slow endpoints: avg_response_time_ms, request_count
            const responseTime = parseFloat(endpoint.avg_response_time || endpoint.avg_response_time_ms || 0);
            const requestCount = parseInt(endpoint.requests || endpoint.request_count || 0);
            const statusClass = getStatusClass(responseTime, 1000, 5000);
            
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-secondary me-2">${endpoint.method}</span>
                            <code class="text-dark">${endpoint.endpoint}</code>
                        </div>
                        <div class="row text-small">
                            <div class="col-6">
                                <small class="text-muted">Requests: <strong>${formatNumber(requestCount)}</strong></small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Avg Time: <strong class="${statusClass}">${responseTime.toFixed(2)}ms</strong></small>
                            </div>
                        </div>
                        ${endpoint.error_rate > 0 ? `
                            <div class="mt-1">
                                <small class="text-danger">Error Rate: ${parseFloat(endpoint.error_rate).toFixed(2)}%</small>
                            </div>
                        ` : ''}
                    </div>
                    <div class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewEndpointDetails('${endpoint.endpoint}', '${endpoint.method}')">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </div>
                </div>
            `;
            
            return div;
        }

        // Update charts with performance history
        async function updateCharts(data) {
            try {
                const historyResponse = await fetch(`/api-performance/api/performance-history?range=${currentTimeRange}`);
                const historyData = await historyResponse.json();
                
                const labels = historyData.history.map(h => new Date(h.hour).toLocaleTimeString());
                const responseTimes = historyData.history.map(h => parseFloat(h.avg_response_time) || 0);
                const requestCounts = historyData.history.map(h => parseInt(h.request_count) || 0);
                
                // Update response time chart
                responseTimeChart.data.labels = labels;
                responseTimeChart.data.datasets[0].data = responseTimes;
                responseTimeChart.update();
                
                // Update request volume chart
                requestVolumeChart.data.labels = labels;
                requestVolumeChart.data.datasets[0].data = requestCounts;
                requestVolumeChart.update();
                
            } catch (error) {
                console.error('Failed to update charts:', error);
            }
        }

        // Auto-refresh functionality
        function startAutoRefresh() {
            if (autoRefreshEnabled) {
                refreshInterval = setInterval(loadDashboardData, 30000); // 30 seconds
            }
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }

        function toggleAutoRefresh() {
            autoRefreshEnabled = !autoRefreshEnabled;
            const indicator = document.getElementById('refreshIndicator');
            const text = document.getElementById('refreshText');
            const icon = document.getElementById('refreshIcon');
            
            if (autoRefreshEnabled) {
                indicator.className = 'bg-primary text-white px-3 py-2 rounded-pill d-flex align-items-center';
                text.textContent = 'Auto-refresh: ON';
                icon.classList.remove('fa-pause');
                icon.classList.add('fa-sync-alt');
                startAutoRefresh();
            } else {
                indicator.className = 'bg-secondary text-white px-3 py-2 rounded-pill d-flex align-items-center';
                text.textContent = 'Auto-refresh: OFF';
                icon.classList.remove('fa-sync-alt');
                icon.classList.add('fa-pause');
                stopAutoRefresh();
            }
        }

        // Utility functions
        function formatNumber(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        }

        function getStatusClass(value, warningThreshold, criticalThreshold) {
            if (value >= criticalThreshold) return 'status-critical';
            if (value >= warningThreshold) return 'status-warning';
            return 'status-healthy';
        }

        function showAlert(message, type = 'info') {
            // Create and show bootstrap alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 1050; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        function viewEndpointDetails(endpoint, method) {
            // Open endpoint analytics HTML view in new tab
            // FIXED: Use clean URLs without encoding since we now support them
            const url = `/api-performance/endpoint/${endpoint}/view?method=${method}&range=${currentTimeRange}`;
            window.open(url, '_blank');
        }

        function exportData() {
            const url = `/api-performance/api/export?format=csv&range=${currentTimeRange}`;
            window.open(url, '_blank');
        }

        // Manual refresh trigger
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                loadDashboardData();
                showAlert('Dashboard refreshed manually', 'success');
            }
        });
    </script>
</body>
</html> 