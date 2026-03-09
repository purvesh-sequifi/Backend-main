<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🖥️ System Metrics API - Performance Monitor</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            min-height: 100vh;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #4facfe;
            margin: 0;
            font-size: 2.5em;
        }
        .header p {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 1.1em;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .metric-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            border-left: 5px solid #4facfe;
        }
        .section-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .metric-item:last-child {
            border-bottom: none;
        }
        .metric-label {
            font-weight: 500;
            color: #495057;
        }
        .metric-value {
            font-weight: bold;
            color: #4facfe;
            font-size: 1.1em;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        .load-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .load-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
            font-weight: bold;
        }
        .redis-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .redis-stat {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .redis-stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 5px;
        }
        .redis-stat-label {
            font-size: 0.9em;
            color: #666;
        }
        .refresh-btn {
            background: #4facfe;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            margin: 10px 5px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .refresh-btn:hover {
            background: #3d8bfe;
            transform: translateY(-2px);
        }
        .timestamp {
            text-align: center;
            color: #666;
            font-style: italic;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .json-data {
            display: none;
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            overflow-x: auto;
            margin-top: 20px;
        }
        .health-indicator {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 5px;
        }
        .health-excellent {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .health-good {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .health-warning {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖥️ System Metrics API</h1>
            <p>Real-time System Performance & Resource Monitoring</p>
            <div style="margin-top: 15px;">
                @php
                    $cpuLoad = $data['system']['load_average'][0] ?? 0;
                    $healthStatus = $cpuLoad < 2 ? 'excellent' : ($cpuLoad < 4 ? 'good' : 'warning');
                    $healthText = $cpuLoad < 2 ? 'Excellent' : ($cpuLoad < 4 ? 'Good' : 'High Load');
                @endphp
                <span class="health-indicator health-{{ $healthStatus }}">
                    <span class="status-indicator status-{{ $healthStatus === 'excellent' ? 'good' : ($healthStatus === 'good' ? 'warning' : 'error') }}"></span>
                    System Health: {{ $healthText }}
                </span>
            </div>
        </div>

        <div class="metrics-grid">
            <!-- Memory Metrics -->
            <div class="metric-section">
                <div class="section-title">💾 Memory Usage</div>
                <div class="metric-item">
                    <span class="metric-label">Current Usage</span>
                    <span class="metric-value">{{ $data['memory']['formatted_current'] }}</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Peak Usage</span>
                    <span class="metric-value">{{ $data['memory']['formatted_peak'] }}</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Raw Current</span>
                    <span class="metric-value">{{ number_format($data['memory']['current_usage']) }} bytes</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Raw Peak</span>
                    <span class="metric-value">{{ number_format($data['memory']['peak_usage']) }} bytes</span>
                </div>
            </div>

            <!-- CPU Metrics -->
            <div class="metric-section">
                <div class="section-title">⚡ CPU Performance</div>
                <div class="metric-item">
                    <span class="metric-label">CPU Cores</span>
                    <span class="metric-value">{{ $data['system']['cpu_count'] ?? 'N/A' }}</span>
                </div>
                @if(isset($data['system']['load_average']))
                <div class="metric-item">
                    <span class="metric-label">Load Average (1m)</span>
                    <span class="metric-value">{{ round($data['system']['load_average'][0], 2) }}</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Load Average (5m)</span>
                    <span class="metric-value">{{ round($data['system']['load_average'][1], 2) }}</span>
                </div>
                <div class="metric-item">
                    <span class="metric-label">Load Average (15m)</span>
                    <span class="metric-value">{{ round($data['system']['load_average'][2], 2) }}</span>
                </div>
                
                @php
                    $loadPercentage = min(100, ($data['system']['load_average'][0] / ($data['system']['cpu_count'] ?? 4)) * 100);
                @endphp
                <div style="margin-top: 15px;">
                    <div class="metric-label">CPU Utilization</div>
                    <div class="load-bar">
                        <div class="load-fill" style="width: {{ $loadPercentage }}%">
                            {{ round($loadPercentage, 1) }}%
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Redis Metrics -->
        @if(isset($data['redis']) && !isset($data['redis']['error']))
        <div class="metric-section" style="margin-bottom: 30px;">
            <div class="section-title">🔴 Redis Performance</div>
            <div class="redis-stats">
                <div class="redis-stat">
                    <div class="redis-stat-value">{{ $data['redis']['memory_usage'] }}</div>
                    <div class="redis-stat-label">Memory Usage</div>
                </div>
                <div class="redis-stat">
                    <div class="redis-stat-value">{{ $data['redis']['memory_peak'] }}</div>
                    <div class="redis-stat-label">Peak Memory</div>
                </div>
                <div class="redis-stat">
                    <div class="redis-stat-value">{{ $data['redis']['ops_per_sec'] }}</div>
                    <div class="redis-stat-label">Ops/Second</div>
                </div>
                <div class="redis-stat">
                    <div class="redis-stat-value">{{ number_format($data['redis']['total_commands']) }}</div>
                    <div class="redis-stat-label">Total Commands</div>
                </div>
                <div class="redis-stat">
                    <div class="redis-stat-value">{{ $data['redis']['connected_clients'] }}</div>
                    <div class="redis-stat-label">Connected Clients</div>
                </div>
                <div class="redis-stat">
                    <div class="redis-stat-value">{{ $data['redis']['hit_rate'] }}%</div>
                    <div class="redis-stat-label">Hit Rate</div>
                </div>
            </div>
        </div>
        @elseif(isset($data['redis']['error']))
        <div class="metric-section" style="margin-bottom: 30px; border-left-color: #dc3545;">
            <div class="section-title">🔴 Redis Status</div>
            <div style="color: #dc3545; text-align: center; padding: 20px;">
                <strong>⚠️ Redis Error:</strong> {{ $data['redis']['error'] }}
            </div>
        </div>
        @endif

        <div style="text-align: center; margin: 30px 0;">
            <a href="/performance-dashboard" class="refresh-btn">🏠 Back to Dashboard</a>
            <a href="javascript:location.reload()" class="refresh-btn">🔄 Refresh Data</a>
            <button onclick="toggleJson()" class="refresh-btn">📄 Show JSON</button>
        </div>

        <div id="jsonData" class="json-data">{{ json_encode($rawData, JSON_PRETTY_PRINT) }}</div>

        <div class="timestamp">
            <strong>Last Updated:</strong> {{ $data['timestamp'] }}<br>
            <strong>System Status:</strong> 
            @if($cpuLoad < 2)
                <span style="color: #28a745;">🟢 Excellent Performance</span>
            @elseif($cpuLoad < 4)
                <span style="color: #ffc107;">🟡 Good Performance</span>
            @else
                <span style="color: #dc3545;">🔴 High Load</span>
            @endif
        </div>
    </div>

    <script>
        function toggleJson() {
            const jsonData = document.getElementById('jsonData');
            const button = event.target;
            
            if (jsonData.style.display === 'none' || jsonData.style.display === '') {
                jsonData.style.display = 'block';
                button.textContent = '📄 Hide JSON';
            } else {
                jsonData.style.display = 'none';
                button.textContent = '📄 Show JSON';
            }
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
