<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Console') · {{ config('app.name', 'Tubagus Mart') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen lg:flex">
        <aside class="admin-sidebar fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-white/10 bg-slate-950/95 backdrop-blur-xl lg:flex">
            <div class="flex h-20 items-center gap-3 border-b border-white/10 px-7">
                <div class="brand-mark flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-lg font-black text-slate-950 shadow-lg shadow-cyan-500/10">TM</div>
                <div>
                    <p class="text-sm font-black tracking-[0.18em] text-white">TUBAGUS MART</p>
                    <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-500">Enterprise Console</p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-6">
                <p class="nav-caption">Workspace</p>
                <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'nav-item-active' : '' }}">
                    <span class="nav-icon">⌂</span><span>Dashboard</span>
                </a>

                <p class="nav-caption mt-7">Operations</p>
                <a href="#" class="nav-item"><span class="nav-icon">↗</span><span>Sales</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">▣</span><span>Purchasing</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">▤</span><span>Inventory</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">◎</span><span>Suppliers</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">◈</span><span>Payables</span></a>

                <p class="nav-caption mt-7">Finance</p>
                <a href="#" class="nav-item"><span class="nav-icon">◫</span><span>Accounting Periods</span><span class="ml-auto rounded-full bg-emerald-400/10 px-2 py-0.5 text-[9px] font-bold text-emerald-300">LIVE</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">⌁</span><span>AP Reconciliation</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">◌</span><span>AP Aging</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">◍</span><span>Audit Trail</span></a>

                <p class="nav-caption mt-7">Administration</p>
                <a href="#" class="nav-item"><span class="nav-icon">□</span><span>Products</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">♙</span><span>Users</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">◇</span><span>Roles & Permissions</span></a>
                <a href="#" class="nav-item"><span class="nav-icon">⚙</span><span>Settings</span></a>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="rounded-2xl border border-white/10 bg-white/[0.035] p-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-300 to-blue-500 text-sm font-black text-slate-950">
                            {{ str($currentUser?->name ?? 'A')->substr(0, 1)->upper() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-white">{{ $currentUser?->name ?? 'Administrator' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $currentUser?->email ?? 'admin@tubagusmart.local' }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="Logout" class="text-slate-500 transition hover:text-white">↪</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1 lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-white/10 bg-slate-950/80 backdrop-blur-xl">
                <div class="flex h-20 items-center justify-between px-5 sm:px-8 lg:px-10">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-cyan-300">{{ $section ?? 'Command Center' }}</p>
                        <h1 class="mt-1 text-lg font-bold tracking-tight text-white">@yield('heading', 'Good evening, Administrator')</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="hidden rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold text-slate-400 sm:block">{{ now()->format('d M Y') }}</div>
                        <button class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/[0.04] text-slate-400 transition hover:border-white/20 hover:text-white" aria-label="Notifications">◔</button>
                    </div>
                </div>
            </header>

            <div class="p-5 sm:p-8 lg:p-10">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
