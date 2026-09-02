<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use App\Services\Payables\SupplierPayableReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApReconciliationController
{
    public function index(Request $request, SupplierPayableReconciliationService $service): View
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;
        $supplierId = isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null;
        $rows = $service->reconcile($supplierId, $from, $to);

        return view('admin/accounting/ap-reconciliation/index', [
            'currentUser' => $request->user(),
            'section' => 'Finance · AP Reconciliation',
            'rows' => $rows,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'supplier_id' => $supplierId,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
            'summary' => [
                'suppliers' => $rows->count(),
                'matched' => $rows->where('is_statement_reconciled', true)->count(),
                'discrepancies' => $rows->where('is_statement_reconciled', false)->count(),
                'outstanding' => round($rows->sum('outstanding'), 2),
            ],
        ]);
    }
}
