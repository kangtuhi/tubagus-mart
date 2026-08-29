<?php

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Reports\Payables\SupplierPayablesAgingReport;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SupplierPayablesAgingService
{
    /**
     * Build the current outstanding payables aging report as of the supplied date.
     */
    public function report(?CarbonInterface $asOf = null): SupplierPayablesAgingReport
    {
        $asOf = $asOf?->copy()->startOfDay() ?? now()->startOfDay();

        $invoices = SupplierInvoice::query()
            ->with('supplier')
            ->whereIn('status', [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
            ])
            ->whereDate('invoice_date', '<=', $asOf->toDateString())
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('number')
            ->get();

        $buckets = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '91_plus' => 0.0,
        ];

        $supplierTotals = [];
        $rows = $invoices->map(function (SupplierInvoice $invoice) use ($asOf, &$buckets, &$supplierTotals): array {
            $outstanding = round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount), 2);
            $dueDate = $invoice->due_date instanceof CarbonInterface
                ? $invoice->due_date
                : ($invoice->due_date ? Carbon::parse($invoice->due_date) : null);

            $daysOverdue = $dueDate?->lt($asOf)
                ? $dueDate->diffInDays($asOf)
                : 0;

            $bucket = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => '91_plus',
            };

            $buckets[$bucket] += $outstanding;

            $supplierId = $invoice->supplier_id;
            if (! isset($supplierTotals[$supplierId])) {
                $supplierTotals[$supplierId] = [
                    'supplier_id' => $supplierId,
                    'supplier_code' => $invoice->supplier->code,
                    'supplier_name' => $invoice->supplier->name,
                    'outstanding' => 0.0,
                ];
            }

            $supplierTotals[$supplierId]['outstanding'] += $outstanding;

            return [
                'invoice' => $invoice,
                'supplier' => $invoice->supplier,
                'due_date' => $dueDate,
                'outstanding' => $outstanding,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
            ];
        });

        $buckets = array_map(static fn (float $amount): float => round($amount, 2), $buckets);
        $supplierTotals = array_values(array_map(
            static function (array $supplier): array {
                $supplier['outstanding'] = round($supplier['outstanding'], 2);

                return $supplier;
            },
            $supplierTotals,
        ));

        return new SupplierPayablesAgingReport(
            asOf: $asOf,
            totalOutstanding: round(array_sum($buckets), 2),
            buckets: $buckets,
            suppliers: Collection::make($supplierTotals)->sortByDesc('outstanding')->values(),
            invoices: $rows,
        );
    }

    public function outstandingInvoices(): Collection
    {
        return SupplierInvoice::query()
            ->with('supplier')
            ->whereIn('status', [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
            ])
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->orderBy('due_date')
            ->orderBy('number')
            ->get();
    }
}
