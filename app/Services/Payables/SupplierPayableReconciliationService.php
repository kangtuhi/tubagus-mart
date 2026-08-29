<?php

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;

class SupplierPayableReconciliationService
{
    /**
     * Reconcile posted supplier invoices against their recorded payments.
     *
     * The result is intentionally derived from invoice totals and paid_amount
     * so callers have one consistent balance calculation for AP reporting.
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
                $outstanding = round(max(0, $invoiceTotal - $paidTotal), 2);

                return [
                    'supplier_id' => $first->supplier_id,
                    'supplier_code' => $first->supplier->code,
                    'supplier_name' => $first->supplier->name,
                    'invoice_count' => $supplierInvoices->count(),
                    'invoice_total' => round($invoiceTotal, 2),
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
