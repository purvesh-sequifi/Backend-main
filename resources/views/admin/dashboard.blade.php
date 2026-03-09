<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Sequifi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Placed after Tailwind CDN to guarantee specificity */
        a.tool-card,
        a.tool-card *,
        a.tool-card div,
        a.tool-card p,
        a.tool-card span,
        a.tool-card h3,
        a.tool-card svg {
            cursor: pointer !important;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(99,102,241,0.35); }
            70% { box-shadow: 0 0 0 10px rgba(99,102,241,0); }
            100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
        }
        .fade-in        { animation: fadeIn 0.5s ease-out both; }
        .fade-in-d1     { animation: fadeIn 0.5s ease-out 0.08s both; }
        .fade-in-d2     { animation: fadeIn 0.5s ease-out 0.16s both; }
        .fade-in-d3     { animation: fadeIn 0.5s ease-out 0.24s both; }
        .fade-in-d4     { animation: fadeIn 0.5s ease-out 0.32s both; }
        .tool-card {
            transition: transform 0.25s cubic-bezier(.4,0,.2,1), box-shadow 0.25s cubic-bezier(.4,0,.2,1);
        }
        .tool-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 48px -12px rgba(0,0,0,0.18);
        }
        .tool-card:active {
            transform: translateY(-2px);
        }
        .cta-btn {
            transition: gap 0.2s, padding-right 0.2s, background 0.2s;
        }
        .tool-card:hover .cta-btn {
            gap: 12px;
            padding-right: 20px;
        }
        .tool-card:hover .cta-arrow {
            transform: translateX(4px);
        }
        .cta-arrow {
            transition: transform 0.2s;
        }
        .live-dot {
            animation: pulse-ring 2s infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

    @include('layouts.admin-nav', ['pageTitle' => 'Home'])

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-6 py-10">

        <!-- Welcome -->
        @php
            $authUser = Auth::guard('feature-flags')->user();
            $firstName = $authUser?->first_name ?? 'Admin';
        @endphp
        <div class="mb-10 fade-in">
            <h2 class="text-3xl font-bold text-slate-800 mb-2">
                Welcome back, <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">{{ $firstName }}</span>!
            </h2>
            <p class="text-slate-500 text-base">Choose a tool below to get started.</p>
        </div>

        <!-- ============ Tool Cards ============ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-7 mb-12">

            <!-- Feature Flags -->
            <a href="{{ route('feature-flags.index') }}" style="cursor:pointer" class="tool-card block rounded-2xl overflow-hidden border-2 border-transparent hover:border-blue-400 focus:outline-none focus:ring-4 focus:ring-blue-300/40 fade-in-d1">
                <div style="cursor:pointer" class="relative bg-gradient-to-br from-blue-600 to-indigo-700 p-7 text-white">
                    <!-- Decorative circles -->
                    <div class="absolute -top-8 -right-8 w-36 h-36 bg-white/10 rounded-full"></div>
                    <div class="absolute bottom-4 right-16 w-16 h-16 bg-white/5 rounded-full"></div>

                    <div class="relative">
                        <!-- Icon + title row -->
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold leading-tight">Feature Flags</h3>
                                <p class="text-blue-200 text-sm font-medium">Application Control</p>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-blue-100 text-sm leading-relaxed mb-6">
                            Toggle application features on or off instantly. Control rollouts, A/B tests, and deployments with a single click.
                        </p>

                        <!-- CTA button -->
                        <span class="cta-btn inline-flex items-center gap-2 bg-white text-blue-700 font-semibold text-sm px-5 py-2.5 rounded-lg shadow-lg shadow-blue-900/20" style="cursor: pointer">
                            Open Feature Flags
                            <svg class="w-4 h-4 cta-arrow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </span>
                    </div>
                </div>
            </a>

            <!-- Queue Dashboard -->
            <a href="{{ url('horizon') }}" style="cursor:pointer" class="tool-card block rounded-2xl overflow-hidden border-2 border-transparent hover:border-purple-400 focus:outline-none focus:ring-4 focus:ring-purple-300/40 fade-in-d2">
                <div style="cursor:pointer" class="relative bg-gradient-to-br from-purple-600 to-fuchsia-700 p-7 text-white">
                    <!-- Decorative circles -->
                    <div class="absolute -top-8 -right-8 w-36 h-36 bg-white/10 rounded-full"></div>
                    <div class="absolute bottom-4 right-16 w-16 h-16 bg-white/5 rounded-full"></div>

                    <div class="relative">
                        <!-- Icon + title row -->
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold leading-tight">Queue Dashboard</h3>
                                <p class="text-purple-200 text-sm font-medium">Performance Monitor</p>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-purple-100 text-sm leading-relaxed mb-6">
                            Monitor background jobs in real-time, inspect failed jobs, and track throughput metrics via Laravel Horizon.
                        </p>

                        <!-- CTA button -->
                        <span class="cta-btn inline-flex items-center gap-2 bg-white text-purple-700 font-semibold text-sm px-5 py-2.5 rounded-lg shadow-lg shadow-purple-900/20" style="cursor: pointer">
                            Open Horizon
                            <svg class="w-4 h-4 cta-arrow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </span>
                    </div>
                </div>
            </a>
        </div>

        <!-- ============ System Info Row ============ -->
        <h3 class="text-lg font-semibold text-slate-700 mb-4 fade-in-d3">System Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10 fade-in-d3">
            <!-- Environment -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0
                    @if(app()->environment('local')) bg-amber-100 @else bg-emerald-100 @endif">
                    @if(app()->environment('local'))
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    @else
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Environment</p>
                    <p class="text-lg font-bold text-slate-800 leading-tight flex items-center gap-2">
                        {{ ucfirst(app()->environment()) }}
                        <span class="inline-block w-2 h-2 rounded-full live-dot
                            @if(app()->environment('local')) bg-amber-400 @else bg-emerald-400 @endif"></span>
                    </p>
                </div>
            </div>

            <!-- Laravel -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Laravel</p>
                    <p class="text-lg font-bold text-slate-800 leading-tight">{{ app()->version() }}</p>
                </div>
            </div>

            <!-- PHP -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center gap-4">
                <div class="w-11 h-11 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">PHP</p>
                    <p class="text-lg font-bold text-slate-800 leading-tight">{{ PHP_VERSION }}</p>
                </div>
            </div>
        </div>

        <!-- ============ Security Notice ============ -->
        <div class="fade-in-d4">
            <div class="bg-slate-800 text-white rounded-xl px-6 py-5 flex items-start gap-4">
                <div class="w-10 h-10 rounded-lg bg-indigo-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-indigo-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <div>
                    <p class="font-semibold text-sm mb-1">Super Admin Access</p>
                    <p class="text-slate-300 text-sm leading-relaxed">
                        You have elevated privileges. All actions across these tools are logged for security audit purposes.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-14 border-t border-slate-200 bg-white/50 backdrop-blur-sm">
        <div class="max-w-6xl mx-auto px-6 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p class="text-slate-500 text-xs">&copy; {{ date('Y') }} Sequifi. All rights reserved.</p>
            <p class="text-slate-400 text-xs">Super Admin Portal</p>
        </div>
    </footer>
</body>
</html>
