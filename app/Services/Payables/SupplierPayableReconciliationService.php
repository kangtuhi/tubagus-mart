<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\SupplierInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SupplierPayableReconciliationService
{
    /**
     * Reconcile posted supplier invoices against payments and adjustments.
     */
    public function reconcile(
        ?int $supplierId = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): Collection {
        $invoices = SupplierInvoice::query()
            ->with('supplier')
            ->whereIn('status', [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
                SupplierInvoiceStatus::PAID,
            ])
            ->when($supplierId !== null, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($to !== null, fn ($query) => $query->whereDate('invoice_date', '<=', $to))
            ->orderBy('supplier_id')
            ->orderBy('invoice_date')
            ->orderBy('number')
            ->get();

        return $invoices
            ->groupBy('supplier_id')
            ->map(function (Collection $supplierInvoices) use ($from, $to): array {
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
                    ...$this->statementIntegrity($first->supplier_id, $from, $to),
                ];
            })
            ->values();
    }

    public function supplier(int $supplierId): ?array
    {
        return $this->reconcile($supplierId)->first();
    }

    private function statementIntegrity(
        int $supplierId,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $statement = app(SupplierStatementService::class)->statement($supplierId, $from, $to);
        $statementBalance = round($statement['closing_balance'], 2);

        $operational = SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
                SupplierInvoiceStatus::PAID,
            ])
            ->when($to !== null, fn ($query) => $query->whereDate('invoice_date', '<=', $to))
            ->get()
            ->sum(function (SupplierInvoice $invoice) use ($to): float {
                $creditQuery = $invoice->adjustments()
                    ->where('type', SupplierPayableAdjustmentType::CREDIT);
                $debitQuery = $invoice->adjustments()
                    ->where('type', SupplierPayableAdjustmentType::DEBIT);
                $paymentQuery = $invoice->payments();

                if ($to !== null) {
                    $creditQuery->whereDate('adjustment_date', '<=', $to);
                    $debitQuery->whereDate('adjustment_date', '<=', $to);
                    $paymentQuery->whereDate('paid_at', '<=', $to);
                }

                $credit = (float) $creditQuery->sum('amount');
                $debit = (float) $debitQuery->sum('amount');
                $paid = (float) $paymentQuery->sum('amount');

                return (float) $invoice->grand_total + $debit - $credit - $paid;
            });
        $operationalBalance = round(max(0, $operational), 2);
        $difference = round($statementBalance - $operationalBalance, 2);

        return [
            'statement_balance' => $statementBalance,
            'operational_balance' => $operationalBalance,
            'reconciliation_difference' => $difference,
            'is_statement_reconciled' => $difference === 0.0,
            'reconciliation_status' => $difference === 0.0 ? 'matched' : 'discrepancy',
        ];
    }
}
