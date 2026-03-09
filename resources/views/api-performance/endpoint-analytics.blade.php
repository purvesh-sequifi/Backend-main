<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Endpoint Analytics - {{ $endpoint }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <style>
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .endpoint-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .percentile-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .percentile-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .timeline-item {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 0 10px 10px 0;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
                 .status-success { background: #d4edda; color: #155724; }
         .status-error { background: #f8d7da; color: #721c24; }
         
         /* Prevent infinite scroll issues */
         body, html {
             overflow-x: hidden;
         }
         
         .chart-container canvas {
             max-height: 400px !important;
         }
         
         /* Ensure proper chart sizing */
         #responseTimeChart {
             height: 400px !important;
             max-height: 400px !important;
         }
    </style>
</head>
<body style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh;">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="endpoint-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-chart-line me-3"></i>
                        API Endpoint Analytics
                    </h1>
                    <h3 class="mb-3">
                        <span class="badge bg-light text-dark">{{ strtoupper($method ?: 'ALL') }}</span>
                        <code style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 8px;">{{ $endpoint }}</code>
                    </h3>
                    <p class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Time Range: <strong>{{ ucfirst($timeRange) }}</strong>
                        <span class="ms-3">
                            <i class="fas fa-calendar me-2"></i>
                            Last Updated: {{ now()->format('M j, Y - g:i A') }}
                        </span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="{{ route('api.performance.dashboard') }}" class="btn btn-light btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Time Range:</label>
                    <select class="form-select" id="timeRange" onchange="updateFilters()">
                        <option value="1h" {{ $timeRange == '1h' ? 'selected' : '' }}>Last Hour</option>
                        <option value="6h" {{ $timeRange == '6h' ? 'selected' : '' }}>Last 6 Hours</option>
                        <option value="24h" {{ $timeRange == '24h' ? 'selected' : '' }}>Last 24 Hours</option>
                        <option value="7d" {{ $timeRange == '7d' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30d" {{ $timeRange == '30d' ? 'selected' : '' }}>Last 30 Days</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">HTTP Method:</label>
                    <select class="form-select" id="method" onchange="updateFilters()">
                        <option value="">All Methods</option>
                        <option value="GET" {{ $method == 'GET' ? 'selected' : '' }}>GET</option>
                        <option value="POST" {{ $method == 'POST' ? 'selected' : '' }}>POST</option>
                        <option value="PUT" {{ $method == 'PUT' ? 'selected' : '' }}>PUT</option>
                        <option value="DELETE" {{ $method == 'DELETE' ? 'selected' : '' }}>DELETE</option>
                        <option value="PATCH" {{ $method == 'PATCH' ? 'selected' : '' }}>PATCH</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        @if(!empty($analytics['basic_stats']))
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($analytics['basic_stats']['total_requests']) }}</div>
                    <div class="metric-label">
                        <i class="fas fa-globe me-2"></i>Total Requests
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="metric-value">{{ $analytics['basic_stats']['avg_response_time_ms'] }}ms</div>
                    <div class="metric-label">
                        <i class="fas fa-tachometer-alt me-2"></i>Avg Response Time
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="metric-value">{{ $analytics['basic_stats']['success_rate_percent'] }}%</div>
                    <div class="metric-label">
                        <i class="fas fa-check-circle me-2"></i>Success Rate
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="metric-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="metric-value">{{ $analytics['basic_stats']['p95_response_time_ms'] }}ms</div>
                    <div class="metric-label">
                        <i class="fas fa-chart-bar me-2"></i>P95 Response Time
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row">
                         <!-- Response Time Chart -->
             <div class="col-lg-8">
                 <div class="chart-container">
                     <h4 class="mb-4">
                         <i class="fas fa-chart-line text-primary me-2"></i>
                         Response Time Over Time
                     </h4>
                     @if(!empty($analytics['timeline']))
                         <div style="position: relative; height: 400px; width: 100%;">
                             <canvas id="responseTimeChart"></canvas>
                         </div>
                     @else
                         <div class="text-center py-5">
                             <i class="fas fa-chart-line text-muted" style="font-size: 3rem;"></i>
                             <p class="text-muted mt-3">No timeline data available for the selected time range.</p>
                         </div>
                     @endif
                 </div>
             </div>

            <!-- Percentiles -->
            @if(!empty($analytics['percentiles']))
            <div class="col-lg-4">
                <div class="chart-container">
                    <h4 class="mb-4">
                        <i class="fas fa-chart-bar text-success me-2"></i>
                        Response Time Percentiles
                    </h4>
                    @foreach($analytics['percentiles'] as $percentile => $value)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold">{{ strtoupper($percentile) }}</span>
                            <span class="text-primary">{{ $value }}ms</span>
                        </div>
                        <div class="percentile-bar">
                            <div class="percentile-fill" style="width: {{ min(100, ($value / max(1, $analytics['basic_stats']['max_response_time_ms'] ?? 1)) * 100) }}%">
                                {{ $value }}ms
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="row">
            <!-- Detailed Stats -->
            @if(!empty($analytics['basic_stats']))
            <div class="col-lg-6">
                <div class="chart-container">
                    <h4 class="mb-4">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        Detailed Statistics
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-globe text-primary me-2"></i><strong>Total Requests</strong></td>
                                    <td class="text-end">{{ number_format($analytics['basic_stats']['total_requests']) }}</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-tachometer-alt text-warning me-2"></i><strong>Avg Response Time</strong></td>
                                    <td class="text-end">{{ $analytics['basic_stats']['avg_response_time_ms'] }}ms</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-arrow-down text-success me-2"></i><strong>Min Response Time</strong></td>
                                    <td class="text-end">{{ $analytics['basic_stats']['min_response_time_ms'] }}ms</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-arrow-up text-danger me-2"></i><strong>Max Response Time</strong></td>
                                    <td class="text-end">{{ $analytics['basic_stats']['max_response_time_ms'] }}ms</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-memory text-info me-2"></i><strong>Avg Memory Usage</strong></td>
                                    <td class="text-end">{{ $analytics['basic_stats']['avg_memory_mb'] }}MB</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-exclamation-triangle text-danger me-2"></i><strong>Error Count</strong></td>
                                    <td class="text-end">{{ $analytics['basic_stats']['error_count'] }}</td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-percentage text-danger me-2"></i><strong>Error Rate</strong></td>
                                    <td class="text-end">{{ $analytics['basic_stats']['error_rate_percent'] }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Timeline Data -->
            @if(!empty($analytics['timeline']))
            <div class="col-lg-6">
                <div class="chart-container">
                    <h4 class="mb-4">
                        <i class="fas fa-clock text-secondary me-2"></i>
                        Request Timeline
                    </h4>
                    <div class="timeline-container" style="max-height: 400px; overflow-y: auto;">
                        @foreach($analytics['timeline'] as $timePoint)
                        <div class="timeline-item">
                            <div class="row">
                                <div class="col-6">
                                    <strong>{{ $timePoint['time'] }}</strong>
                                </div>
                                <div class="col-6 text-end">
                                    <span class="status-badge status-success">
                                        {{ $timePoint['request_count'] }} requests
                                    </span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="fas fa-tachometer-alt me-1"></i>
                                        {{ $timePoint['avg_response_time'] }}ms avg
                                    </small>
                                </div>
                                <div class="col-6 text-end">
                                    @if($timePoint['error_count'] > 0)
                                        <span class="status-badge status-error">
                                            {{ $timePoint['error_count'] }} errors
                                        </span>
                                    @else
                                        <span class="status-badge status-success">
                                            <i class="fas fa-check"></i> No errors
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Error Breakdown -->
        @if(!empty($analytics['error_breakdown']))
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <h4 class="mb-4">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                        Error Breakdown
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Status Code</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($analytics['error_breakdown'] as $error)
                                <tr>
                                    <td><span class="badge bg-danger">{{ $error['status_code'] }}</span></td>
                                    <td>{{ $error['count'] }}</td>
                                    <td>{{ $error['percentage'] }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Export and Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container text-center">
                    <h5 class="mb-3">Export & Actions</h5>
                    <div class="btn-group" role="group">
                        <a href="{{ route('api.performance.endpoint.analytics', ['endpoint' => urlencode($endpoint)]) }}?{{ http_build_query(request()->query()) }}" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-download me-2"></i>Download JSON
                        </a>
                        <button class="btn btn-outline-success" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                        <button class="btn btn-outline-info" onclick="location.reload()">
                            <i class="fas fa-sync me-2"></i>Refresh Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
                 // Response Time Chart
         @if(!empty($analytics['timeline']))
         document.addEventListener('DOMContentLoaded', function() {
             const timelineData = @json($analytics['timeline']);
             const chartElement = document.getElementById('responseTimeChart');
             
             if (!chartElement || !timelineData || timelineData.length === 0) {
                 console.warn('Chart element not found or no timeline data available');
                 return;
             }
             
                          const ctx = chartElement.getContext('2d');
             
             try {
                 new Chart(ctx, {
             type: 'line',
             data: {
                 labels: timelineData.map(item => item.time),
                 datasets: [{
                     label: 'Avg Response Time (ms)',
                     data: timelineData.map(item => item.avg_response_time),
                     borderColor: 'rgb(75, 192, 192)',
                     backgroundColor: 'rgba(75, 192, 192, 0.1)',
                     tension: 0.4,
                     fill: true,
                     borderWidth: 2,
                     pointRadius: 4,
                     pointHoverRadius: 6
                 }, {
                     label: 'Request Count',
                     data: timelineData.map(item => item.request_count),
                     borderColor: 'rgb(255, 99, 132)',
                     backgroundColor: 'rgba(255, 99, 132, 0.1)',
                     tension: 0.4,
                     yAxisID: 'y1',
                     borderWidth: 2,
                     pointRadius: 4,
                     pointHoverRadius: 6
                 }]
             },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 interaction: {
                     mode: 'index',
                     intersect: false,
                 },
                 scales: {
                     x: {
                         display: true,
                         title: {
                             display: true,
                             text: 'Time'
                         }
                     },
                     y: {
                         type: 'linear',
                         display: true,
                         position: 'left',
                         title: {
                             display: true,
                             text: 'Response Time (ms)'
                         },
                         beginAtZero: true
                     },
                     y1: {
                         type: 'linear',
                         display: true,
                         position: 'right',
                         title: {
                             display: true,
                             text: 'Request Count'
                         },
                         beginAtZero: true,
                         grid: {
                             drawOnChartArea: false,
                         },
                     }
                 },
                 plugins: {
                     legend: {
                         display: true,
                         position: 'top'
                     },
                     title: {
                         display: false
                     }
                 },
                 elements: {
                     point: {
                         radius: 3
                     }
                                  }
             }
         });
             } catch (error) {
                 console.error('Failed to initialize chart:', error);
                 // Show fallback message
                 chartElement.parentElement.innerHTML = `
                     <div class="text-center py-5">
                         <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                         <p class="text-muted mt-3">Unable to load chart. Please refresh the page.</p>
                     </div>
                 `;
             }
         }); // End DOMContentLoaded
         @endif

        // Filter Updates
        function updateFilters() {
            const timeRange = document.getElementById('timeRange').value;
            const method = document.getElementById('method').value;
            
            const url = new URL(window.location);
            url.searchParams.set('range', timeRange);
            
            if (method) {
                url.searchParams.set('method', method);
            } else {
                url.searchParams.delete('method');
            }
            
            window.location.href = url.toString();
        }

                 // Auto-refresh every 60 seconds (reduced to prevent scroll issues)
         setTimeout(() => {
             location.reload();
         }, 60000);
    </script>
</body>
</html> 