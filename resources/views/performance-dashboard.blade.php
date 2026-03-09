<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Performance Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .chart-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        .active-batches {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .batch-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .batch-item:last-child {
            border-bottom: none;
        }
        .progress-bar {
            width: 200px;
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            transition: width 0.3s ease;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-running { background-color: #2196F3; color: white; }
        .status-completed { background-color: #4CAF50; color: white; }
        .status-failed { background-color: #f44336; color: white; }
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .refresh-btn:hover {
            background: #5a6fd8;
        }
        .last-updated {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-top: 20px;
        }
        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            margin: 30px 0;
            border-radius: 1px;
        }
        .quick-links a {
            display: inline-block;
            margin: 5px;
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .quick-links a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Sales Performance Dashboard</h1>
            <p>Real-time monitoring of Octane+Swoole+Redis+Horizon optimization</p>
            <button class="refresh-btn" onclick="refreshData()">🔄 Refresh Data</button>
        </div>

        <div class="stats-grid" id="statsGrid">
            <!-- Loading placeholder -->
            <div class="stat-card">
                <div class="stat-value">⏳</div>
                <div class="stat-label">Loading...</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">📊</div>
                <div class="stat-label">Fetching Data...</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">🔄</div>
                <div class="stat-label">Please Wait...</div>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-title">📊 Performance Trends (Last 24 Hours)</div>
            <canvas id="performanceChart" width="400" height="200"></canvas>
        </div>

        <div class="active-batches">
            <div class="chart-title">⚡ Recent Job Performance</div>
            <div id="activeBatches">
                <!-- Recent jobs will be loaded here -->
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-title">🖥️ System Metrics</div>
            <div id="systemMetrics" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <!-- System metrics will be loaded here -->
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-title">📋 Queue Status</div>
            <div id="queueStatus" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                <!-- Queue status will be loaded here -->
            </div>
        </div>

        <div class="section-divider"></div>
        
        <div class="chart-container">
            <div class="chart-title">🔗 Quick Access Links</div>
            <div class="quick-links">
                <a href="/performance/job-status" target="_blank">📊 Job Status API</a>
                <a href="/performance/system-metrics" target="_blank">🖥️ System Metrics API</a>
                <a href="/horizon" target="_blank">🌅 Horizon Dashboard</a>
                <a href="/api-performance" target="_blank">⚡ API Performance</a>
            </div>
            <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                <strong>📋 All-in-One Dashboard:</strong> This page consolidates all monitoring data in real-time. 
                No need to check multiple URLs - everything is here!
                <br><br>
                <strong>🔄 Auto-Refresh:</strong> Data updates every 30 seconds automatically.
                <br>
                <strong>📱 Mobile Friendly:</strong> Responsive design works on all devices.
            </div>
        </div>

        <div class="last-updated" id="lastUpdated">
            Last updated: Loading...
        </div>
    </div>

    <script>
        let performanceChart;
        
        async function fetchData(endpoint) {
            try {
                const response = await fetch(`/performance/${endpoint}?json=1`, {
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                console.log(`Fetched ${endpoint}:`, data); // Debug log
                return data.status ? data.data : null;
            } catch (error) {
                console.error(`Error fetching ${endpoint}:`, error);
                return null;
            }
        }

        async function loadDashboardData() {
            console.log('Loading dashboard data...');
            const jobStatus = await fetchData('job-status');
            console.log('Job status received:', jobStatus);
            
            if (jobStatus) {
                updateStatsGrid(jobStatus.summary);
                updateActiveBatches(jobStatus.recent_jobs);
                updateQueueStatus(jobStatus.queue_status);
            } else {
                console.error('No job status data received');
                // Show loading message
                document.getElementById('statsGrid').innerHTML = `
                    <div class="stat-card" style="grid-column: 1 / -1;">
                        <div class="stat-value">⏳</div>
                        <div class="stat-label">Loading job data...</div>
                    </div>
                `;
            }
        }

        async function loadSystemMetrics() {
            console.log('Loading system metrics...');
            const systemMetrics = await fetchData('system-metrics');
            console.log('System metrics received:', systemMetrics);
            
            if (systemMetrics) {
                updateSystemMetrics(systemMetrics);
            } else {
                console.error('No system metrics data received');
                // Show loading message
                document.getElementById('systemMetrics').innerHTML = `
                    <div class="stat-card" style="grid-column: 1 / -1;">
                        <div class="stat-value">⏳</div>
                        <div class="stat-label">Loading system metrics...</div>
                    </div>
                `;
            }
        }

        async function loadActiveBatches() {
            const jobStatus = await fetchData('job-status');
            if (jobStatus) {
                updateActiveBatches(jobStatus.recent_jobs);
            }
        }

        function updateStatsGrid(stats) {
            const statsGrid = document.getElementById('statsGrid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${stats.completed_chunks || 0}</div>
                    <div class="stat-label">Completed Chunks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${(stats.total_processed_pids || 0).toLocaleString()}</div>
                    <div class="stat-label">PIDs Processed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.average_duration_seconds || 0}s</div>
                    <div class="stat-label">Avg Duration</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.average_throughput || 0}</div>
                    <div class="stat-label">PIDs/sec Throughput</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.success_rate || 0}%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.progress_percentage || 0}%</div>
                    <div class="stat-label">Overall Progress</div>
                </div>
            `;
        }

        function updatePerformanceChart(hourlyData) {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            
            if (performanceChart) {
                performanceChart.destroy();
            }

            const hours = Object.keys(hourlyData).sort();
            const throughputData = hours.map(hour => hourlyData[hour].average_throughput || 0);
            const durationData = hours.map(hour => hourlyData[hour].average_duration || 0);

            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: hours.map(hour => new Date(hour).toLocaleTimeString()),
                    datasets: [{
                        label: 'Throughput (PIDs/sec)',
                        data: throughputData,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        yAxisID: 'y'
                    }, {
                        label: 'Avg Duration (sec)',
                        data: durationData,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Throughput (PIDs/sec)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Duration (seconds)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }

        function updateActiveBatches(recentJobs) {
            const container = document.getElementById('activeBatches');
            
            if (!recentJobs || recentJobs.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No recent job data</p>';
                return;
            }

            container.innerHTML = recentJobs.map(job => `
                <div class="batch-item">
                    <div>
                        <strong>Chunk ${job.total_pids} PIDs</strong><br>
                        <small>Completed: ${job.timestamp}</small>
                    </div>
                    <div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%"></div>
                        </div>
                        <small>Success: ${job.success_count}/${job.total_pids}</small>
                    </div>
                    <div>
                        <span class="status-badge status-completed">
                            COMPLETED
                        </span><br>
                        <small>${(job.duration_ms / 1000).toFixed(1)}s • ${job.throughput.toFixed(2)} PID/s</small>
                    </div>
                </div>
            `).join('');
        }

        function updateSystemMetrics(metrics) {
            const container = document.getElementById('systemMetrics');
            
            const memoryUsage = metrics.memory ? metrics.memory.formatted_current : 'N/A';
            const cpuLoad = metrics.system && metrics.system.load_average ? metrics.system.load_average[0].toFixed(2) : 'N/A';
            const cpuCount = metrics.system ? metrics.system.cpu_count : 'N/A';
            const redisMemory = metrics.redis ? metrics.redis.memory_usage : 'N/A';
            const redisOps = metrics.redis ? metrics.redis.ops_per_sec : 'N/A';
            const redisHitRate = metrics.redis ? metrics.redis.hit_rate + '%' : 'N/A';
            
            container.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${memoryUsage}</div>
                    <div class="stat-label">Memory Usage</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${cpuLoad}</div>
                    <div class="stat-label">CPU Load (${cpuCount} cores)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${redisMemory}</div>
                    <div class="stat-label">Redis Memory</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${redisOps}</div>
                    <div class="stat-label">Redis Ops/sec</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${redisHitRate}</div>
                    <div class="stat-label">Redis Hit Rate</div>
                </div>
            `;
        }

        function updateQueueStatus(queueStatus) {
            const container = document.getElementById('queueStatus');
            
            if (!queueStatus) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No queue data available</p>';
                return;
            }
            
            container.innerHTML = Object.keys(queueStatus).map(queueName => {
                const queue = queueStatus[queueName];
                const statusColor = queue.status === 'active' ? '#4CAF50' : '#666';
                
                return `
                    <div class="stat-card" style="border-left: 4px solid ${statusColor};">
                        <div class="stat-value">${queue.pending_jobs || 0}</div>
                        <div class="stat-label">${queueName}</div>
                        <small style="color: ${statusColor};">${queue.status || 'unknown'}</small>
                    </div>
                `;
            }).join('');
        }

        async function refreshData() {
            document.getElementById('lastUpdated').textContent = 'Last updated: Refreshing...';
            
            try {
                await Promise.all([
                    loadDashboardData(),
                    loadSystemMetrics()
                ]);
                
                document.getElementById('lastUpdated').textContent = `Last updated: ${new Date().toLocaleString()}`;
            } catch (error) {
                console.error('Error refreshing data:', error);
                document.getElementById('lastUpdated').textContent = `Last updated: Error - ${new Date().toLocaleString()}`;
                
                // Show error message in stats grid
                document.getElementById('statsGrid').innerHTML = `
                    <div class="stat-card" style="grid-column: 1 / -1; background: #f8d7da; border-left-color: #dc3545;">
                        <div class="stat-value" style="color: #dc3545;">⚠️</div>
                        <div class="stat-label">Error loading data. Check console for details.</div>
                    </div>
                `;
            }
        }

        // Add error handling for initial load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded, starting data fetch...');
            
            // Add timeout fallback in case fetch fails
            setTimeout(function() {
                if (document.getElementById('lastUpdated').textContent.includes('Loading')) {
                    console.log('Data fetch timeout, showing fallback message');
                    document.getElementById('statsGrid').innerHTML = `
                        <div class="stat-card" style="grid-column: 1 / -1; background: #fff3cd; border-left-color: #ffc107;">
                            <div class="stat-value" style="color: #856404;">⚠️</div>
                            <div class="stat-label">Dashboard loading slowly. Try refreshing or check individual API pages below.</div>
                        </div>
                    `;
                    document.getElementById('lastUpdated').textContent = 'Dashboard timeout - Try manual refresh';
                }
            }, 10000); // 10 second timeout
            
            refreshData();
        });

        // Auto-refresh every 30 seconds
        setInterval(refreshData, 30000);
        
        // Add global error handler
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
            document.getElementById('lastUpdated').textContent = `Error: ${e.error.message}`;
        });
    </script>
</body>
</html>
