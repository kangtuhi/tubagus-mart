@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Good evening, Administrator')

@section('content')
    <div class="mx-auto max-w-[1600px] space-y-7">
        <section class="hero-panel relative overflow-hidden rounded-[2rem] border border-white/10 p-7 sm:p-9">
            <div class="hero-glow absolute -right-24 -top-32 h-80 w-80 rounded-full bg-cyan-400/15 blur-3xl"></div>
            <div class="relative grid gap-8 lg:grid-cols-[1.4fr_0.6fr] lg:items-end">
                <div>
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_12px_currentColor]"></span>
                        System Operational
                    </div>
                    <h2 class="max-w-3xl text-3xl font-black tracking-[-0.04em] text-white sm:text-5xl">Your supermarket,<br><span class="text-gradient">under intelligent control.</span></h2>
                    <p class="mt-5 max-w-2xl text-sm leading-7 text-slate-400 sm:text-base">A single command center for sales, purchasing, inventory, suppliers and financial control — designed for Tubagus Mart's next level of scale.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-black/20 p-5 backdrop-blur-sm">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Current accounting period</p>
                    <div class="mt-3 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-2xl font-black text-white">August 2026</p>
                            <p class="mt-1 text-xs text-slate-500">01 Aug — 31 Aug 2026</p>
                        </div>
                        <span class="status-open">OPEN</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['label' => 'Today Sales', 'value' => 'Rp 48.920.000', 'delta' => '+12.8%', 'icon' => '↗'], ['label' => 'Outstanding Payables', 'value' => 'Rp 126.450.000', 'delta' => '18 invoices', 'icon' => '◈'], ['label' => 'Inventory Value', 'value' => 'Rp 1.84 M', 'delta' => '2,481 SKUs', 'icon' => '▤'], ['label' => 'Gross Margin', 'value' => '18.64%', 'delta' => '+1.9 pts', 'icon' => '◒']] as $stat)
                <article class="stat-card group">
                    <div class="flex items-start justify-between">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.05] text-lg text-cyan-300">{{ $stat['icon'] }}</div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">{{ $stat['delta'] }}</span>
                    </div>
                    <p class="mt-7 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-2xl font-black tracking-tight text-white">{{ $stat['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.45fr_0.75fr]">
            <article class="panel-card p-6 sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="eyebrow">Financial overview</p>
                        <h3 class="mt-1 text-lg font-bold text-white">Revenue performance</h3>
                    </div>
                    <button class="rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2 text-xs font-semibold text-slate-400">Last 30 days⌄</button>
                </div>
                <div class="mt-8 flex h-64 items-end gap-2 sm:gap-3">
                    @foreach ([42,55,48,68,59,73,62,80,71,88,76,94,82,91,86,100,88,96,79,92,98,84,93,89,100,95,100,91,97,100] as $height)
                        <div class="group relative flex h-full flex-1 items-end">
                            <div class="w-full rounded-t-lg bg-gradient-to-t from-cyan-500/30 to-cyan-300/80 transition-all duration-300 group-hover:from-cyan-400/60 group-hover:to-white" style="height: {{ $height }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex justify-between text-[10px] font-semibold uppercase tracking-widest text-slate-600"><span>03 Aug</span><span>17 Aug</span><span>02 Sep</span></div>
            </article>

            <article class="panel-card p-6 sm:p-7">
                <div>
                    <p class="eyebrow">Control center</p>
                    <h3 class="mt-1 text-lg font-bold text-white">Priority actions</h3>
                </div>
                <div class="mt-5 space-y-3">
                    <div class="action-row"><span class="action-dot bg-amber-300"></span><div><p class="text-sm font-bold text-white">18 supplier invoices</p><p class="text-xs text-slate-500">Awaiting payment</p></div><span class="text-slate-600">›</span></div>
                    <div class="action-row"><span class="action-dot bg-cyan-300"></span><div><p class="text-sm font-bold text-white">Accounting reconciliation</p><p class="text-xs text-slate-500">August period</p></div><span class="text-slate-600">›</span></div>
                    <div class="action-row"><span class="action-dot bg-emerald-300"></span><div><p class="text-sm font-bold text-white">Stock health</p><p class="text-xs text-slate-500">97.4% healthy</p></div><span class="text-slate-600">›</span></div>
                    <div class="action-row"><span class="action-dot bg-violet-300"></span><div><p class="text-sm font-bold text-white">Audit activity</p><p class="text-xs text-slate-500">12 events today</p></div><span class="text-slate-600">›</span></div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-3">
            <article class="panel-card p-6">
                <p class="eyebrow">Operations</p>
                <h3 class="mt-1 text-lg font-bold text-white">Today's pulse</h3>
                <div class="mt-6 space-y-5">
                    <div><div class="mb-2 flex justify-between text-xs"><span class="text-slate-400">Sales target</span><span class="font-bold text-white">82%</span></div><div class="meter"><span style="width:82%"></span></div></div>
                    <div><div class="mb-2 flex justify-between text-xs"><span class="text-slate-400">Stock availability</span><span class="font-bold text-white">94%</span></div><div class="meter"><span style="width:94%"></span></div></div>
                    <div><div class="mb-2 flex justify-between text-xs"><span class="text-slate-400">Order fulfillment</span><span class="font-bold text-white">97%</span></div><div class="meter"><span style="width:97%"></span></div></div>
                </div>
            </article>
            <article class="panel-card p-6">
                <p class="eyebrow">Security</p>
                <h3 class="mt-1 text-lg font-bold text-white">Access posture</h3>
                <div class="mt-6 flex items-center gap-4 rounded-2xl border border-emerald-300/10 bg-emerald-300/5 p-4"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-300/10 text-emerald-300">✓</div><div><p class="text-sm font-bold text-white">All systems secure</p><p class="text-xs text-slate-500">Permissions enforced</p></div></div>
                <p class="mt-5 text-xs leading-6 text-slate-500">Sensitive accounting actions remain permission-gated and fully auditable.</p>
            </article>
            <article class="panel-card p-6">
                <p class="eyebrow">Activity</p>
                <h3 class="mt-1 text-lg font-bold text-white">Latest event</h3>
                <div class="mt-6"><p class="text-sm font-bold text-white">Accounting period control updated</p><p class="mt-2 text-xs leading-6 text-slate-500">Reopen authorization boundary verified successfully.</p><p class="mt-4 text-[10px] font-bold uppercase tracking-widest text-slate-600">Just now · System</p></div>
            </article>
        </section>
    </div>
@endsection
