<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Feature Flags Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .toggle-switch { transition: all 0.3s ease; }
        .toggle-switch:hover:not(:disabled) { transform: scale(1.05); }
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .status-badge { font-size: 11px; letter-spacing: 0.5px; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    
    @include('layouts.admin-nav', ['pageTitle' => $company->name ?? 'Feature Flags'])
    
    <!-- Main Content Container -->
    <div class="py-4"></div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-6 py-8">
        <!-- Flash Messages -->
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center fade-in">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center fade-in">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <!-- Stats Overview -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-slate-200 p-5 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Total Features</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Enabled</p>
                        <p class="text-2xl font-bold text-emerald-600">{{ $stats['enabled'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Disabled</p>
                        <p class="text-2xl font-bold text-slate-400">{{ $stats['disabled'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Needs Attention</p>
                        <p class="text-2xl font-bold text-amber-600">{{ $stats['disabled_in_use'] }}</p>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-amber-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Tables by Category -->
        @foreach($categories as $categoryKey => $category)
            @if(count($category['features']) > 0)
                <div class="bg-white rounded-xl border border-slate-200 mb-6 overflow-hidden card-hover">
                    <!-- Category Header -->
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center
                                @if($categoryKey === 'sales') bg-indigo-100 @elseif($categoryKey === 'payroll') bg-emerald-100 @else bg-purple-100 @endif">
                                @if($category['icon'] === 'chart-line')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                                    </svg>
                                @elseif($category['icon'] === 'credit-card')
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-purple-600">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-slate-800">{{ $category['label'] }}</h2>
                                <p class="text-sm text-slate-500">{{ $category['description'] }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Features Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Feature</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Usage</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($category['features'] as $featureKey => $feature)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <!-- Feature Name & Description -->
                                        <td class="px-6 py-4">
                                            <div class="flex items-start gap-3">
                                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0
                                                    @if($feature['is_enabled']) bg-emerald-100 @else bg-slate-100 @endif">
                                                    @if($feature['is_enabled'])
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-emerald-600">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-400">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="font-medium text-slate-800">{{ $feature['name'] }}</p>
                                                    <p class="text-sm text-slate-500 mt-0.5 max-w-md">{{ $feature['description'] }}</p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="px-6 py-4">
                                            @php
                                                $badgeClasses = match($feature['state']['key']) {
                                                    'enabled' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'enabled_in_use' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'disabled_in_use' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                    default => 'bg-slate-50 text-slate-500 border-slate-200',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $badgeClasses }} status-badge">
                                                @if($feature['state']['key'] === 'enabled')
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                                @elseif($feature['state']['key'] === 'enabled_in_use')
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5"></span>
                                                @elseif($feature['state']['key'] === 'disabled_in_use')
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                                @else
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                                                @endif
                                                {{ $feature['state']['label'] }}
                                            </span>
                                            
                                            @if($feature['state']['warning'])
                                                <p class="text-xs text-amber-600 mt-1.5 flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 mr-1">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                    </svg>
                                                    Data exists
                                                </p>
                                            @endif
                                        </td>

                                        <!-- Usage Info -->
                                        <td class="px-6 py-4">
                                            @if($feature['is_in_use'])
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-sm font-medium bg-slate-100 text-slate-700">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1.5 text-slate-500">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                                        </svg>
                                                        {{ $feature['usage_count'] }} records
                                                    </span>
                                                    <button 
                                                        type="button"
                                                        onclick="showUsageModal('{{ $featureKey }}', '{{ $feature['name'] }}')"
                                                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium hover:underline"
                                                    >
                                                        View Details
                                                    </button>
                                                </div>
                                            @else
                                                <span class="text-sm text-slate-400">No usage data</span>
                                            @endif
                                        </td>

                                        <!-- Toggle Action -->
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" action="{{ route('feature-flags.toggle') }}" class="inline" id="form-{{ $featureKey }}">
                                                @csrf
                                                <input type="hidden" name="feature" value="{{ $featureKey }}">
                                                <input type="hidden" name="enabled" value="{{ $feature['is_enabled'] ? '0' : '1' }}">
                                                
                                                @php
                                                    $canToggle = true;
                                                    $toggleTitle = $feature['is_enabled'] ? 'Click to disable' : 'Click to enable';
                                                    
                                                    if ($feature['is_enabled'] && $feature['is_in_use'] && !($feature['can_disable_when_in_use'] ?? true)) {
                                                        $canToggle = false;
                                                        $toggleTitle = 'Cannot disable: feature is in use';
                                                    }
                                                @endphp

                                                <button 
                                                    type="{{ $canToggle ? 'submit' : 'button' }}"
                                                    @if(!$canToggle)
                                                        onclick="showUsageModal('{{ $featureKey }}', '{{ $feature['name'] }}')"
                                                    @endif
                                                    class="toggle-switch relative inline-flex h-7 w-12 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ $feature['is_enabled'] ? 'bg-emerald-500' : 'bg-slate-200' }} {{ $canToggle ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }}"
                                                    title="{{ $toggleTitle }}"
                                                >
                                                    <span class="sr-only">Toggle {{ $feature['name'] }}</span>
                                                    <span 
                                                        class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out {{ $feature['is_enabled'] ? 'translate-x-5' : 'translate-x-0' }}"
                                                    ></span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endforeach

        <!-- Empty State -->
        @php
            $hasAnyFeatures = false;
            foreach ($categories as $cat) {
                if (count($cat['features']) > 0) {
                    $hasAnyFeatures = true;
                    break;
                }
            }
        @endphp

        @if(!$hasAnyFeatures)
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-slate-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-2">No Features Configured</h3>
                <p class="text-slate-500">Features will appear here once they are added to the registry.</p>
            </div>
        @endif

        <!-- Status Legend -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 mt-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Status Legend</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-emerald-50 text-emerald-700 border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                        Enabled
                    </span>
                    <span class="text-xs text-slate-500">Ready to use</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-blue-50 text-blue-700 border-blue-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5"></span>
                        Enabled + In Use
                    </span>
                    <span class="text-xs text-slate-500">Active</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-slate-50 text-slate-500 border-slate-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                        Disabled
                    </span>
                    <span class="text-xs text-slate-500">Not active</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border bg-amber-50 text-amber-700 border-amber-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                        In Use + Disabled
                    </span>
                    <span class="text-xs text-slate-500">Has data</span>
                </div>
            </div>
        </div>
    </main>

    <!-- Usage Details Modal -->
    <div id="usageModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden overflow-y-auto h-full w-full z-50 transition-opacity duration-300">
        <div class="relative top-20 mx-auto max-w-2xl fade-in">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden mx-4">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-600">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800" id="modalTitle">Usage Details</h3>
                            <p class="text-sm text-slate-500">Feature usage breakdown</p>
                        </div>
                    </div>
                    <button onclick="closeUsageModal()" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <!-- Modal Content -->
                <div id="modalContent" class="p-6">
                    <div class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 mx-auto text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-slate-500 mt-3">Loading usage details...</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end">
                    <button onclick="closeUsageModal()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 font-medium text-sm transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="max-w-7xl mx-auto px-6 py-8 text-center">
        <p class="text-sm text-slate-400">Feature Flags Dashboard &copy; {{ date('Y') }} &middot; Powered by Laravel Pennant</p>
    </footer>

    <script>
        function showUsageModal(featureKey, featureName) {
            const modal = document.getElementById('usageModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalContent = document.getElementById('modalContent');
            
            modalTitle.textContent = featureName;
            modalContent.innerHTML = `
                <div class="text-center py-8">
                    <svg class="animate-spin h-8 w-8 mx-auto text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-slate-500 mt-3">Loading usage details...</p>
                </div>
            `;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            fetch(`/feature-flags/${featureKey}/usage`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    renderUsageDetails(data.data, data.can_disable_when_in_use);
                } else {
                    modalContent.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            ${data.message || 'Failed to load usage details'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                modalContent.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        Failed to load usage details
                    </div>
                `;
            });
        }
        
        function renderUsageDetails(data, canDisableWhenInUse) {
            const modalContent = document.getElementById('modalContent');
            
            // Modules Section
            let modulesHtml = '';
            if (data.modules && data.modules.length > 0) {
                modulesHtml = `
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2 text-slate-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                            Affected Modules
                        </h4>
                        <div class="grid grid-cols-2 gap-2">
                            ${data.modules.map(m => `
                                <div class="p-3 bg-slate-50 rounded-lg border border-slate-100">
                                    <p class="font-medium text-slate-700 text-sm">${m.name}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">${m.description}</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            // Records Table Section
            let recordsHtml = '';
            if (data.records && Object.keys(data.records).length > 0) {
                const recordRows = Object.entries(data.records).map(([key, record]) => `
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-2.5 text-sm text-slate-600">${record.label}</td>
                        <td class="py-2.5 text-sm font-medium text-right ${record.count > 0 ? 'text-indigo-600' : 'text-slate-400'}">${record.count}</td>
                    </tr>
                `).join('');
                
                recordsHtml = `
                    <div class="mb-6">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-2 text-slate-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                            Usage Breakdown
                        </h4>
                        <div class="bg-slate-50 rounded-xl border border-slate-100 overflow-hidden">
                            <table class="w-full">
                                <tbody>
                                    ${recordRows}
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-100">
                                        <td class="py-3 px-3 text-sm font-semibold text-slate-700">Total Records</td>
                                        <td class="py-3 px-3 text-sm font-bold text-right ${data.total_count > 0 ? 'text-indigo-600' : 'text-slate-600'}">${data.total_count}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            // Status Message
            let messageHtml = '';
            if (data.message) {
                const isSuccess = data.can_disable;
                const bgColor = isSuccess ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200';
                const textColor = isSuccess ? 'text-emerald-700' : 'text-amber-700';
                const iconColor = isSuccess ? 'text-emerald-500' : 'text-amber-500';
                const icon = isSuccess 
                    ? `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 ${iconColor}"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
                    : `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 ${iconColor}"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`;
                
                messageHtml = `
                    <div class="border rounded-xl p-4 flex items-start gap-3 ${bgColor}">
                        ${icon}
                        <p class="text-sm ${textColor}">${data.message}</p>
                    </div>
                `;
            }
            
            modalContent.innerHTML = modulesHtml + recordsHtml + messageHtml;
        }
        
        function closeUsageModal() {
            document.getElementById('usageModal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        // Close modal when clicking outside
        document.getElementById('usageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUsageModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUsageModal();
            }
        });
    </script>
</body>
</html>
