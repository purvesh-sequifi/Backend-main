<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Job Status API - Sales Performance</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
            margin: 0;
            font-size: 2.5em;
        }
        .header p {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 1.1em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border-left: 5px solid #667eea;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .stat-label {
            color: #666;
            font-size: 1em;
            font-weight: 500;
        }
        .progress-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .progress-bar {
            width: 100%;
            height: 25px;
            background-color: #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            margin: 15px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .recent-jobs {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .job-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .job-item:last-child {
            margin-bottom: 0;
        }
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .queue-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #28a745;
        }
        .queue-card.idle {
            border-left-color: #6c757d;
        }
        .queue-card.active {
            border-left-color: #28a745;
        }
        .section-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .refresh-btn {
            background: #667eea;
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
            background: #5a6fd8;
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
        .json-toggle {
            text-align: center;
            margin: 20px 0;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Job Status API</h1>
            <p>Real-time Sales Recalculation Performance Monitoring</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{{ $data['summary']['completed_chunks'] }}</div>
                <div class="stat-label">Completed Chunks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ number_format($data['summary']['total_processed_pids']) }}</div>
                <div class="stat-label">PIDs Processed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $data['summary']['success_rate'] }}%</div>
                <div class="stat-label">Success Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $data['summary']['average_throughput'] }}</div>
                <div class="stat-label">PIDs/sec Throughput</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $data['summary']['average_duration_seconds'] }}s</div>
                <div class="stat-label">Avg Duration</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ number_format($data['summary']['estimated_remaining_pids']) }}</div>
                <div class="stat-label">Remaining PIDs</div>
            </div>
        </div>

        <div class="progress-section">
            <div class="section-title">📈 Overall Progress</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: {{ $data['summary']['progress_percentage'] }}%">
                    {{ $data['summary']['progress_percentage'] }}%
                </div>
            </div>
            <p style="text-align: center; margin: 10px 0 0 0; color: #666;">
                {{ number_format($data['summary']['total_processed_pids']) }} of {{ number_format($data['summary']['estimated_total_pids']) }} PIDs completed
            </p>
        </div>

        <div class="recent-jobs">
            <div class="section-title">⚡ Recent Job Performance</div>
            @foreach(array_slice($data['recent_jobs'], -5) as $job)
            <div class="job-item">
                <div>
                    <strong>{{ $job['total_pids'] }} PIDs</strong><br>
                    <small>{{ $job['timestamp'] }}</small>
                    @if(isset($job['batch_id']) && $job['batch_id'])
                        <br><small style="color: #666;">Batch: {{ substr($job['batch_id'], -8) }}</small>
                    @endif
                </div>
                <div>
                    <strong>{{ $job['success_count'] }}/{{ $job['total_pids'] }} Success</strong><br>
                    <small>{{ round($job['duration_ms'] / 1000, 1) }}s • {{ round($job['throughput'], 2) }} PIDs/sec</small>
                    @if(isset($job['pids']) && !empty($job['pids']))
                        <br><details style="margin-top: 5px;">
                            <summary style="cursor: pointer; color: #007bff; font-size: 0.8em;">
                                <i class="fas fa-list"></i> Show {{ count($job['pids']) }} PIDs
                            </summary>
                            <div style="margin-top: 5px; padding: 5px; background: #f8f9fa; border-radius: 3px; font-family: monospace; font-size: 0.75em; max-height: 100px; overflow-y: auto;">
                                @foreach($job['pids'] as $index => $pid)
                                    <span style="display: inline-block; margin: 1px 2px; padding: 1px 4px; background: #e9ecef; border-radius: 2px;">{{ $pid }}</span>@if(($index + 1) % 5 == 0)<br>@endif
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="section-title">📋 Queue Status</div>
        <div class="queue-grid">
            @foreach($data['queue_status'] as $queueName => $queue)
            <div class="queue-card {{ $queue['status'] }}">
                <div class="stat-value" style="font-size: 1.8em;">{{ $queue['pending_jobs'] }}</div>
                <div class="stat-label">{{ $queueName }}</div>
                <small style="color: {{ $queue['status'] === 'active' ? '#28a745' : '#6c757d' }};">
                    {{ strtoupper($queue['status']) }}
                </small>
            </div>
            @endforeach
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="/performance-dashboard" class="refresh-btn">🏠 Back to Dashboard</a>
            <a href="javascript:location.reload()" class="refresh-btn">🔄 Refresh Data</a>
            <button onclick="toggleJson()" class="refresh-btn">📄 Show JSON</button>
        </div>

        <div class="json-toggle">
            <div id="jsonData" class="json-data">{{ json_encode($rawData, JSON_PRETTY_PRINT) }}</div>
        </div>

        <div class="timestamp">
            <strong>Last Updated:</strong> {{ $data['timestamp'] }}<br>
            <strong>Last Job Completed:</strong> {{ $data['summary']['last_job_completed'] ?? 'N/A' }}
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
