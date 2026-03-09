<!-- Common Admin Navigation Bar -->
<nav class="bg-white/95 backdrop-blur-lg border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-6 py-3.5">
        <div class="flex justify-between items-center">
            <!-- Logo & Brand -->
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                </div>
                <div>
                    <h1 class="text-base font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">Sequifi Admin Portal</h1>
                    <p class="text-xs text-slate-500">{{ $pageTitle ?? 'Administration' }}</p>
                </div>
            </div>

            <!-- Navigation Links & User Info -->
            <div class="flex items-center gap-4">
                <!-- Admin Portal Link -->
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-sm {{ Request::is('admin') || Request::is('admin/*') ? 'text-indigo-600 bg-indigo-50' : 'text-slate-600 hover:text-indigo-600 hover:bg-indigo-50' }} transition-all px-3 py-2 rounded-lg font-medium">
                    <span>🏠</span>
                    <span>Admin Portal</span>
                </a>
                
                <!-- Feature Flags Link -->
                <a href="{{ route('feature-flags.index') }}" class="flex items-center gap-2 text-sm {{ Request::is('feature-flags') || Request::is('feature-flags/*') ? 'text-blue-600 bg-blue-50' : 'text-slate-600 hover:text-blue-600 hover:bg-blue-50' }} transition-all px-3 py-2 rounded-lg font-medium">
                    <span>🎯</span>
                    <span>Feature Flags</span>
                </a>
                
                <!-- Horizon Link -->
                <a href="{{ url('horizon') }}" class="flex items-center gap-2 text-sm {{ Request::is('horizon') || Request::is('horizon/*') ? 'text-purple-600 bg-purple-50' : 'text-slate-600 hover:text-purple-600 hover:bg-purple-50' }} transition-all px-3 py-2 rounded-lg font-medium">
                    <span>📊</span>
                    <span>Queue Dashboard</span>
                </a>
                
                <div class="h-6 w-px bg-slate-300"></div>
                
                <!-- User Info -->
                @php
                    $authUser = Auth::guard('feature-flags')->user();
                    $firstName = $authUser?->first_name ?? 'Admin';
                    $lastName = $authUser?->last_name ?? 'User';
                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                @endphp
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm shadow-md">
                        {{ $initials }}
                    </div>
                    <span class="text-sm font-medium text-slate-700">{{ $firstName }} {{ $lastName }}</span>
                </div>
                
                <!-- Logout Button -->
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all hover:shadow-lg font-medium text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
