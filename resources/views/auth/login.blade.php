<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In - Support Ticket System</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-100">

    <div class="max-w-md w-full space-y-6">
        <!-- Logo & Header -->
        <div class="text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600 text-white shadow-lg mb-4">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Support Ticket System</h2>
            <p class="mt-2 text-sm text-slate-600">SLA Management, Dynamic Assignment & Conversation Hub</p>
        </div>

        <!-- Session Status & Errors -->
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 p-4 border border-rose-200 text-sm text-rose-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Login Card -->
        <div class="bg-white py-8 px-6 shadow-xl rounded-2xl border border-slate-200 sm:px-10">
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                        class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-slate-700">Remember me</label>
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-xs text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        Sign in
                    </button>
                </div>
            </form>

            <!-- 1-Click Demo Login Shortcuts -->
            <div class="mt-6 pt-6 border-t border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider text-center mb-3">
                    Fast Demo 1-Click Access
                </p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <form action="{{ route('demo.login') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="admin@example.com">
                        <button type="submit" class="w-full py-2 px-3 rounded-lg border border-purple-200 bg-purple-50 text-purple-700 font-semibold hover:bg-purple-100 transition text-left">
                            👑 Admin User
                        </button>
                    </form>

                    <form action="{{ route('demo.login') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="agent1@example.com">
                        <button type="submit" class="w-full py-2 px-3 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 font-semibold hover:bg-blue-100 transition text-left">
                            🎧 Agent 1 (Sarah)
                        </button>
                    </form>

                    <form action="{{ route('demo.login') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="agent2@example.com">
                        <button type="submit" class="w-full py-2 px-3 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 font-semibold hover:bg-blue-100 transition text-left">
                            🎧 Agent 2 (John)
                        </button>
                    </form>

                    <form action="{{ route('demo.login') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="customer1@example.com">
                        <button type="submit" class="w-full py-2 px-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 font-semibold hover:bg-emerald-100 transition text-left">
                            👤 Customer (Acme)
                        </button>
                    </form>
                </div>
                <p class="text-[11px] text-slate-400 text-center mt-2">Password for all seeded accounts is <code class="text-slate-600 bg-slate-100 px-1 py-0.5 rounded">password</code></p>
            </div>

            <div class="mt-4 text-center">
                <span class="text-xs text-slate-500">Need a customer account?</span>
                <a href="{{ route('register') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 ml-1">Create an account</a>
            </div>
        </div>
    </div>

</body>
</html>

