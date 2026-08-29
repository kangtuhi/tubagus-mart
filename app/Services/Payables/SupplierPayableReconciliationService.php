<?php

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;

class SupplierPayableReconciliationService
{
    /**
     * Reconcile posted supplier invoices against payments and adjustments.
     */
    public function reconcile(?int $supplierId = null): Collection
    {
        $invoices = SupplierInvoice::query()
            ->with('supplier')
            ->whereIn('status', [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
                SupplierInvoiceStatus::PAID,
            ])
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->orderBy('supplier_id')
            ->orderBy('invoice_date')
            ->orderBy('number')
            ->get();

        return $invoices
            ->groupBy('supplier_id')
            ->map(function (Collection $supplierInvoices): array {
                $first = $supplierInvoices->first();
                $invoiceTotal = $supplierInvoices->sum(fn (SupplierInvoice $invoice): float => (float) $invoice->grand_total);
                $paidTotal = $supplierInvoices->sum(fn (SupplierInvoice $invoice): float => (float) $invoice->paid_amount);
                $creditTotal = $supplierInvoices->sum(fn (SupplierInvoice $invoice): float => (float) $invoice->adjustments()
                    ->where('type', SupplierPayableAdjustmentType::CREDIT)
                    ->sum('amount'));
                $debitTotal = $supplierInvoices->sum(fn (SupplierInvoice $invoice): float => (float) $invoice->adjustments()
                    ->where('type', SupplierPayableAdjustmentType::DEBIT)
                    ->sum('amount'));
                $adjustedTotal = round($invoiceTotal + $debitTotal - $creditTotal, 2);
                $outstanding = round(max(0, $adjustedTotal - $paidTotal), 2);

                return [
                    'supplier_id' => $first->supplier_id,
                    'supplier_code' => $first->supplier->code,
                    'supplier_name' => $first->supplier->name,
                    'invoice_count' => $supplierInvoices->count(),
                    'invoice_total' => round($invoiceTotal, 2),
                    'credit_total' => round($creditTotal, 2),
                    'debit_total' => round($debitTotal, 2),
                    'adjusted_total' => $adjustedTotal,
                    'paid_total' => round($paidTotal, 2),
                    'outstanding' => $outstanding,
                    'is_reconciled' => $outstanding === 0.0,
                ];
            })
            ->values();
    }

    public function supplier(int $supplierId): ?array
    {
        return $this->reconcile($supplierId)->first();
    }
}
