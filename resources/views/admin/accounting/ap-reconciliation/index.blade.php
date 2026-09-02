@extends('layouts.admin')

@section('title', 'AP Reconciliation')
@section('heading', 'AP Reconciliation Console')

@section('content')
    <div class="space-y-8">
        <div class="flex flex-col justify-between gap-5 xl:flex-row xl:items-end">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Accounts Payable Control</p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-white">Supplier reconciliation</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Cross-check posted supplier invoices, payments, payable adjustments, and the supplier statement ledger before an accounting period can be closed.</p>
            </div>
            <div class="rounded-2xl border border-cyan-400/20 bg-cyan-400/5 px-4 py-3 text-xs text-cyan-200">Integrity gate · LIVE</div>
        </div>

        <form method="GET" class="rounded-3xl border border-white/10 bg-white/[0.035] p-5 shadow-2xl shadow-black/10">
            <div class="grid gap-4 md:grid-cols-4">
                <label class="block md:col-span-2">
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Supplier</span>
                    <select name="supplier_id" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-cyan-300 focus:outline-none">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected($filters['supplier_id'] === $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">From</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-cyan-300 focus:outline-none">
                </label>
                <label class="block">
                    <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">To</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-cyan-300 focus:outline-none">
                </label>
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="submit" class="rounded-xl bg-white px-4 py-2.5 text-xs font-black text-slate-950 transition hover:bg-cyan-100">Run reconciliation</button>
                <a href="{{ route('admin.accounting.ap-reconciliation.index') }}" class="rounded-xl border border-white/10 px-4 py-2.5 text-xs font-bold text-slate-300 transition hover:border-white/20 hover:text-white">Reset</a>
            </div>
            @if ($errors->any())
                <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-400/5 p-4 text-sm text-rose-200">{{ $errors->first() }}</div>
            @endif
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Suppliers checked', 'value' => $summary['suppliers'], 'tone' => 'text-white'],
                ['label' => 'Matched', 'value' => $summary['matched'], 'tone' => 'text-emerald-300'],
                ['label' => 'Discrepancies', 'value' => $summary['discrepancies'], 'tone' => 'text-amber-300'],
                ['label' => 'Outstanding AP', 'value' => 'Rp '.number_format($summary['outstanding'], 2, ',', '.'), 'tone' => 'text-cyan-300'],
            ] as $card)
                <div class="rounded-3xl border border-white/10 bg-white/[0.035] p-5">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-black {{ $card['tone'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035]">
            <div class="border-b border-white/10 px-6 py-5">
                <p class="text-sm font-black text-white">Supplier control matrix</p>
                <p class="mt-1 text-xs text-slate-500">Operational payable balance versus statement balance and payment ledger integrity.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="bg-white/[0.025] text-[10px] uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Supplier</th>
                            <th class="px-4 py-4">Invoices</th>
                            <th class="px-4 py-4">Adjusted AP</th>
                            <th class="px-4 py-4">Paid</th>
                            <th class="px-4 py-4">Outstanding</th>
                            <th class="px-4 py-4">Statement Δ</th>
                            <th class="px-4 py-4">Payment Δ</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($rows as $row)
                            <tr class="transition hover:bg-white/[0.025]">
                                <td class="px-6 py-4"><p class="font-bold text-white">{{ $row['supplier_name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['supplier_code'] }}</p></td>
                                <td class="px-4 py-4 text-slate-300">{{ $row['invoice_count'] }}</td>
                                <td class="px-4 py-4 text-slate-300">Rp {{ number_format($row['adjusted_total'], 2, ',', '.') }}</td>
                                <td class="px-4 py-4 text-slate-300">Rp {{ number_format($row['paid_total'], 2, ',', '.') }}</td>
                                <td class="px-4 py-4 font-semibold text-cyan-200">Rp {{ number_format($row['outstanding'], 2, ',', '.') }}</td>
                                <td class="px-4 py-4 {{ $row['reconciliation_difference'] == 0.0 ? 'text-emerald-300' : 'text-amber-300' }}">Rp {{ number_format($row['reconciliation_difference'], 2, ',', '.') }}</td>
                                <td class="px-4 py-4 {{ $row['payment_ledger_difference'] == 0.0 ? 'text-emerald-300' : 'text-amber-300' }}">Rp {{ number_format($row['payment_ledger_difference'], 2, ',', '.') }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide {{ $row['is_statement_reconciled'] ? 'bg-emerald-400/10 text-emerald-300' : 'bg-amber-400/10 text-amber-300' }}">{{ $row['reconciliation_status'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-16 text-center text-sm text-slate-500">No posted supplier invoices match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5 text-xs leading-6 text-slate-400">
            <span class="font-bold text-white">Closing rule:</span> a period must have zero statement discrepancy and zero payment-ledger discrepancy across the AP scope before the accounting-period closing gate can pass.
        </div>
    </div>
@endsection
