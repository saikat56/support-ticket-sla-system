<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Support Ticket System') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col min-h-screen">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Left Nav: Brand & Primary Links -->
                <div class="flex items-center space-x-8">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2 text-indigo-600 font-bold text-xl tracking-tight">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                        <span class="text-slate-900 font-extrabold">Support<span class="text-indigo-600">Desk</span></span>
                    </a>

                    <nav class="hidden md:flex space-x-4">
                        <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('tickets.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('tickets.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                            Tickets
                        </a>
                        @if(auth()->user()->canManageTickets())
                            <a href="{{ route('imports.index') }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('imports.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                                CSV Import
                            </a>
                        @endif
                    </nav>
                </div>

                <!-- Right Nav: Actions & User Menu -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('tickets.create') }}" class="inline-flex items-center px-3.5 py-1.5 border border-transparent text-sm font-semibold rounded-lg shadow-xs text-white bg-indigo-600 hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Ticket
                    </a>

                    <!-- User Role Badge & Dropdown -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center space-x-2 pl-3 pr-2 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 transition text-sm">
                            <span class="font-medium text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold
                                {{ auth()->user()->isAdmin() ? 'bg-purple-100 text-purple-800' : (auth()->user()->isAgent() ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800') }}">
                                {{ auth()->user()->role->label() }}
                            </span>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="open" x-cloak class="absolute right-0 mt-2 w-64 rounded-xl shadow-lg bg-white ring-1 ring-black/5 divide-y divide-slate-100 py-1 z-50">
                            <div class="px-4 py-2 text-xs text-slate-500">
                                Signed in as <strong class="text-slate-800">{{ auth()->user()->email }}</strong>
                            </div>

                            <!-- Fast Demo Switcher for Evaluators -->
                            <div class="px-2 py-2">
                                <div class="px-2 text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                                    Quick Role Switcher
                                </div>
                                <form action="{{ route('demo.login') }}" method="POST" class="space-y-1">
                                    @csrf
                                    <button type="submit" name="email" value="admin@example.com" class="w-full text-left px-2 py-1 text-xs rounded hover:bg-purple-50 hover:text-purple-700 flex items-center justify-between">
                                        <span>👑 Admin (All access)</span>
                                    </button>
                                    <button type="submit" name="email" value="agent1@example.com" class="w-full text-left px-2 py-1 text-xs rounded hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between">
                                        <span>🎧 Agent 1 (Sarah Connor)</span>
                                    </button>
                                    <button type="submit" name="email" value="agent2@example.com" class="w-full text-left px-2 py-1 text-xs rounded hover:bg-blue-50 hover:text-blue-700 flex items-center justify-between">
                                        <span>🎧 Agent 2 (John Miller)</span>
                                    </button>
                                    <button type="submit" name="email" value="customer1@example.com" class="w-full text-left px-2 py-1 text-xs rounded hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-between">
                                        <span>👤 Customer (Acme Corp)</span>
                                    </button>
                                </form>
                            </div>

                            <div class="py-1">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Global Notifications / Alerts -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 flex items-center justify-between text-emerald-800 mb-4" x-data="{ show: true }" x-show="show">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-medium">{{ session('status') }}</span>
                </div>
                <button @click="show = false" class="text-emerald-600 hover:text-emerald-900">&times;</button>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 p-4 border border-rose-200 text-rose-800 mb-4" x-data="{ show: true }" x-show="show">
                <div class="flex justify-between items-start">
                    <div class="flex items-start space-x-2">
                        <svg class="w-5 h-5 text-rose-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-semibold text-sm">Please check the errors below:</p>
                            <ul class="list-disc list-inside text-sm mt-1 space-y-0.5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <button @click="show = false" class="text-rose-600 hover:text-rose-900">&times;</button>
                </div>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
            <div>
                Support Ticket & SLA System &bull; Laravel 11 &bull; Tailwind CSS &bull; Docker
            </div>
            <div>
                Business Hours: <span class="font-semibold">Sun–Thu 09:00–18:00</span>
            </div>
        </div>
    </footer>

</body>
</html>

