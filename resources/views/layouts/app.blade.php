<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Orca Med') — توزيع الأدوية</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">

<div class="flex flex-row-reverse min-h-screen">

    {{-- ===== Sidebar ===== --}}
    <aside id="sidebar"
        class="fixed top-0 right-0 h-full w-64 bg-blue-950 text-white flex flex-col z-40 transition-transform duration-300">

        {{-- Logo --}}
        <div class="flex items-center justify-center px-5 py-5 border-b border-blue-800">
            <img src="{{ asset('images/orca-med-logo.jpeg') }}" alt="Orca Med Logo" class="w-full h-auto object-contain" onerror="this.style.display='none';">
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto py-3">
            @include('partials.sidebar-nav')
        </nav>

        {{-- User info --}}
        <div class="border-t border-blue-800 px-4 py-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-sm font-semibold shrink-0">
                    {{ mb_substr(auth()->user()->name ?? 'U', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">{{ auth()->user()->name ?? '' }}</div>
                    <div class="text-xs text-blue-300">{{ auth()->user()->role?->value ?? '' }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit"
                    class="w-full text-right text-xs text-blue-300 hover:text-white py-1 transition-colors">
                    تسجيل الخروج ←
                </button>
            </form>
        </div>
    </aside>

    {{-- ===== Main area (offset for sidebar) ===== --}}
    <div class="flex-1 flex flex-col mr-64">

        {{-- Topbar --}}
        <header class="bg-white shadow-sm px-6 py-3 flex items-center justify-between sticky top-0 z-30">
            <div class="flex items-center gap-3">
                {{-- Mobile sidebar toggle --}}
                <button id="sidebar-toggle" class="lg:hidden text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500">مرحباً،</span>
                <span class="text-sm font-semibold text-slate-700">{{ auth()->user()->name ?? '' }}</span>
            </div>
        </header>

        {{-- Flash messages --}}
        <div class="px-6 pt-4">
            @if(session('status'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Page content --}}
        <main class="flex-1 px-6 pb-8">
            @yield('content')
        </main>

    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebar-toggle');
    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('translate-x-full');
        });
    }
</script>

@stack('scripts')
</body>
</html>
