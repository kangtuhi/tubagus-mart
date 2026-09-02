@extends('layouts.admin')

@section('title', 'Accounting Periods')
@section('heading', 'Accounting Period Control')

@section('content')
    <div class="mx-auto max-w-[1600px] space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-5 py-4 text-sm font-semibold text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <section class="hero-panel relative overflow-hidden rounded-[2rem] border border-white/10 p-7 sm:p-9">
            <div class="hero-glow absolute -right-24 -top-32 h-80 w-80 rounded-full bg-cyan-400/15 blur-3xl"></div>
            <div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div>
                    <p class="eyebrow">Finance · Period control</p>
                    <h2 class="mt-2 text-3xl font-black tracking-[-0.04em] text-white sm:text-4xl">Month-end control center</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">Accounting periods are protected by AP reconciliation before close, while reopen operations remain explicitly authorized and auditable.</p>
                </div>
                @if ($currentPeriod)
                    <div class="min-w-[280px] rounded-2xl border border-white/10 bg-black/20 p-5 backdrop-blur-sm">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Current period</p>
                        <p class="mt-2 text-xl font-black text-white">{{ $currentPeriod->start_date->format('d M Y') }} — {{ $currentPeriod->end_date->format('d M Y') }}</p>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <span class="{{ $currentPeriod->isOpen() ? 'status-open' : 'rounded-full bg-slate-400/10 px-2.5 py-1 text-[10px] font-bold tracking-widest text-slate-400' }}">{{ $currentPeriod->status->value }}</span>
                            @if ($currentPeriod->isOpen())
                                <a href="{{ route('admin.accounting.periods.gate', $currentPeriod) }}" class="text-xs font-bold text-cyan-300 hover:text-white">Review closing gate →</a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if ($closingGate)
            <section class="panel-card p-6 sm:p-7">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div>
                        <p class="eyebrow">AP reconciliation gate</p>
                        <h3 class="mt-1 text-lg font-bold text-white">{{ $closingGate['can_close'] ? 'Ready for controlled close' : 'Close blocked by reconciliation' }}</h3>
                        <p class="mt-2 text-sm text-slate-500">{{ $closingGate['checks']['ap_reconciliation']['supplier_count'] }} suppliers checked · {{ $closingGate['checks']['ap_reconciliation']['discrepancy_count'] }} discrepancies</p>
                    </div>
                    <a href="{{ route('admin.accounting.periods.gate', $currentPeriod) }}" class="rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2.5 text-xs font-bold text-white transition hover:border-cyan-300/30 hover:text-cyan-200">Open gate detail</a>
                </div>
            </section>
        @endif

        <section class="panel-card overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5 sm:px-7">
                <p class="eyebrow">Ledger timeline</p>
                <h3 class="mt-1 text-lg font-bold text-white">Accounting periods</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead class="border-b border-white/10 bg-white/[0.02] text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Period</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Closed by</th>
                            <th class="px-6 py-4">Closed at</th>
                            <th class="px-6 py-4 text-right">Control</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($periods as $period)
                            <tr class="transition hover:bg-white/[0.025]">
                                <td class="px-6 py-5">
                                    <p class="text-sm font-bold text-white">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</p>
                                    @if ($period->closing_reason)
                                        <p class="mt-1 text-xs text-slate-500">{{ $period->closing_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5">
                                    @if ($period->isOpen())
                                        <span class="status-open">OPEN</span>
                                    @else
                                        <span class="rounded-full bg-slate-400/10 px-2.5 py-1 text-[10px] font-bold tracking-widest text-slate-400">CLOSED</span>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-sm text-slate-400">{{ $period->closedBy?->name ?? 'System' }}</td>
                                <td class="px-6 py-5 text-sm text-slate-400">{{ $period->closed_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="px-6 py-5 text-right">
                                    @if ($period->isOpen())
                                        <a href="{{ route('admin.accounting.periods.gate', $period) }}" class="text-xs font-bold text-cyan-300 hover:text-white">Closing gate →</a>
                                    @else
                                        @can('accounting.period.reopen')
                                            <a href="#reopen-{{ $period->id }}" class="text-xs font-bold text-amber-300 hover:text-white">Reopen</a>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No accounting periods have been created yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
