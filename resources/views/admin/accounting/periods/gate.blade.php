@extends('layouts.admin')

@section('title', 'Closing Gate')
@section('heading', 'AP Reconciliation Closing Gate')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-300/20 bg-rose-300/10 px-5 py-4 text-sm text-rose-200">
                <p class="font-bold">Control action failed.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="hero-panel rounded-[2rem] border border-white/10 p-7 sm:p-9">
            <p class="eyebrow">Month-end · {{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</p>
            <div class="mt-3 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <h2 class="text-3xl font-black tracking-[-0.04em] text-white">Reconciliation gate</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">The period can only close when every supplier payable reconciliation through the period end date is matched.</p>
                </div>
                <span class="{{ $gate['can_close'] ? 'status-open' : 'rounded-full bg-rose-400/10 px-3 py-1.5 text-[10px] font-bold tracking-widest text-rose-300' }}">{{ $gate['can_close'] ? 'GATE PASSED' : 'GATE BLOCKED' }}</span>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-3">
            <article class="stat-card"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Suppliers checked</p><p class="mt-2 text-2xl font-black text-white">{{ $gate['checks']['ap_reconciliation']['supplier_count'] }}</p></article>
            <article class="stat-card"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Discrepancies</p><p class="mt-2 text-2xl font-black text-white">{{ $gate['checks']['ap_reconciliation']['discrepancy_count'] }}</p></article>
            <article class="stat-card"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">AP status</p><p class="mt-2 text-2xl font-black {{ $gate['checks']['ap_reconciliation']['status'] === 'passed' ? 'text-emerald-300' : 'text-rose-300' }}">{{ strtoupper($gate['checks']['ap_reconciliation']['status']) }}</p></article>
        </section>

        @if ($gate['discrepancies'])
            <section class="panel-card overflow-hidden">
                <div class="border-b border-white/10 px-6 py-5"><p class="eyebrow">Exceptions</p><h3 class="mt-1 text-lg font-bold text-white">Resolve before closing</h3></div>
                <div class="divide-y divide-white/5">
                    @foreach ($gate['discrepancies'] as $discrepancy)
                        <div class="px-6 py-5"><p class="text-sm font-bold text-white">Supplier #{{ $discrepancy['supplier_id'] }}</p><p class="mt-1 text-xs text-rose-300">{{ $discrepancy['reconciliation_status'] }}</p></div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="panel-card p-6 sm:p-7">
            <div class="flex flex-col justify-between gap-6 lg:flex-row">
                @if ($period->isOpen() && $gate['can_close'] && $currentUser?->hasPermission('accounting.period.close'))
                    <form method="POST" action="{{ route('admin.accounting.periods.close', $period) }}" class="flex-1 space-y-4">
                        @csrf
                        <div><p class="eyebrow">Close period</p><h3 class="mt-1 text-lg font-bold text-white">Commit month-end close</h3></div>
                        <textarea name="reason" rows="3" maxlength="500" class="w-full rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-cyan-300/40" placeholder="Optional closing note..."></textarea>
                        <button type="submit" class="rounded-xl bg-white px-5 py-3 text-xs font-black text-slate-950 transition hover:bg-cyan-100">Close accounting period</button>
                    </form>
                @elseif ($period->isOpen())
                    <div class="flex-1"><p class="eyebrow">Close period</p><h3 class="mt-1 text-lg font-bold text-white">Closing is not available</h3><p class="mt-2 text-sm leading-6 text-slate-500">The AP gate must pass and the current user must have the accounting period close permission.</p></div>
                @endif

                @if ($period->isClosed() && $currentUser?->hasPermission('accounting.period.reopen'))
                    <form method="POST" action="{{ route('admin.accounting.periods.reopen', $period) }}" class="flex-1 space-y-4 border-t border-white/10 pt-6 lg:border-l lg:border-t-0 lg:pl-7 lg:pt-0">
                        @csrf
                        <div><p class="eyebrow">Controlled reopen</p><h3 class="mt-1 text-lg font-bold text-white">Reopen with an explicit reason</h3></div>
                        <textarea name="reason" rows="3" maxlength="500" required class="w-full rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white outline-none placeholder:text-slate-600 focus:border-amber-300/40" placeholder="Why must this closed period be reopened?"></textarea>
                        <button type="submit" class="rounded-xl border border-amber-300/20 bg-amber-300/10 px-5 py-3 text-xs font-black text-amber-200 transition hover:bg-amber-300/20">Reopen accounting period</button>
                    </form>
                @endif
            </div>
        </section>
    </div>
@endsection
