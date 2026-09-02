<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · {{ config('app.name', 'Tubagus Mart') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
    <main class="relative flex min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(34,211,238,.12),transparent_28%),radial-gradient(circle_at_82%_82%,rgba(59,130,246,.10),transparent_30%)]"></div>
        <div class="absolute -left-40 top-1/4 h-96 w-96 rounded-full bg-cyan-400/5 blur-3xl"></div>
        <div class="absolute -right-40 bottom-1/4 h-96 w-96 rounded-full bg-blue-500/5 blur-3xl"></div>

        <section class="relative hidden w-1/2 flex-col justify-between border-r border-white/10 bg-white/[.015] p-12 lg:flex xl:p-16">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-lg font-black text-slate-950 shadow-xl shadow-cyan-500/10">TM</div>
                    <div>
                        <p class="text-sm font-black tracking-[.18em] text-white">TUBAGUS MART</p>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[.24em] text-slate-500">Enterprise Console</p>
                    </div>
                </div>
            </div>

            <div class="max-w-xl">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.2em] text-emerald-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_12px_currentColor]"></span>
                    Secure workspace
                </div>
                <h1 class="text-5xl font-black leading-[1.02] tracking-[-.05em] text-white xl:text-6xl">
                    The command center for <span class="text-gradient">Tubagus Mart.</span>
                </h1>
                <p class="mt-6 max-w-lg text-sm leading-7 text-slate-400 xl:text-base">
                    Control sales, purchasing, inventory, suppliers and financial operations from one secure enterprise workspace.
                </p>
                <div class="mt-10 grid max-w-lg grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/[.07] bg-white/[.03] p-4"><p class="text-lg font-black text-white">24/7</p><p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Control</p></div>
                    <div class="rounded-2xl border border-white/[.07] bg-white/[.03] p-4"><p class="text-lg font-black text-white">100%</p><p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Auditable</p></div>
                    <div class="rounded-2xl border border-white/[.07] bg-white/[.03] p-4"><p class="text-lg font-black text-white">RBAC</p><p class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Protected</p></div>
                </div>
            </div>

            <p class="text-[10px] font-semibold uppercase tracking-[.2em] text-slate-600">Authorized personnel only · Tubagus Mart</p>
        </section>

        <section class="relative flex w-full items-center justify-center px-6 py-12 lg:w-1/2 xl:px-16">
            <div class="w-full max-w-md">
                <div class="mb-10 lg:hidden">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-sm font-black text-slate-950">TM</div>
                        <div><p class="text-sm font-black tracking-[.18em] text-white">TUBAGUS MART</p><p class="text-[10px] uppercase tracking-[.24em] text-slate-500">Enterprise Console</p></div>
                    </div>
                </div>

                <div class="mb-8">
                    <p class="eyebrow">Welcome back</p>
                    <h2 class="mt-2 text-3xl font-black tracking-[-.04em] text-white">Sign in to your workspace</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Use your authorized Tubagus Mart account to continue.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 rounded-2xl border border-rose-300/20 bg-rose-300/5 p-4 text-sm text-rose-200">
                        <p class="font-bold">Unable to sign in</p>
                        <p class="mt-1 text-xs leading-5 text-rose-200/70">{{ $errors->first() }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required class="w-full rounded-2xl border border-white/10 bg-white/[.04] px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-slate-700 focus:border-cyan-300/50 focus:bg-white/[.06] focus:ring-4 focus:ring-cyan-300/5" placeholder="you@tubagusmart.local">
                    </div>
                    <div>
                        <div class="mb-2 flex items-center justify-between"><label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Password</label><span class="text-[10px] font-semibold text-slate-700">Protected</span></div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="w-full rounded-2xl border border-white/10 bg-white/[.04] px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-slate-700 focus:border-cyan-300/50 focus:bg-white/[.06] focus:ring-4 focus:ring-cyan-300/5" placeholder="••••••••••••">
                    </div>
                    <label class="flex cursor-pointer items-center gap-3 text-xs text-slate-500"><input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-white/10 bg-white/5 text-cyan-300 focus:ring-cyan-300/20"> Keep me signed in</label>
                    <button type="submit" class="group flex w-full items-center justify-center gap-3 rounded-2xl bg-white px-5 py-3.5 text-sm font-black text-slate-950 shadow-xl shadow-cyan-500/10 transition duration-300 hover:-translate-y-0.5 hover:bg-cyan-100 focus:outline-none focus:ring-4 focus:ring-cyan-300/10">
                        Continue to console <span class="transition group-hover:translate-x-1">→</span>
                    </button>
                </form>

                <div class="mt-8 flex items-center gap-3 rounded-2xl border border-white/[.06] bg-white/[.02] p-4"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-300/10 text-emerald-300">✓</div><p class="text-[11px] leading-5 text-slate-500">Your session is protected by server-side authentication and role/permission controls.</p></div>
                <p class="mt-8 text-center text-[10px] font-semibold uppercase tracking-[.18em] text-slate-700">Tubagus Mart · Internal Operations</p>
            </div>
        </section>
    </main>
</body>
</html>
