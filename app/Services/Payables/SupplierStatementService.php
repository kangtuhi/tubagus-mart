<?php

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SupplierStatementService
{
    /**
     * Build an auditable supplier statement from the AP transaction ledger.
     *
     * Positive movements increase the amount owed to the supplier; negative
     * movements reduce it. Draft and void invoices are intentionally excluded.
     */
    public function statement(
        int $supplierId,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $transactions = $this->transactions($supplierId);
        $openingBalance = $from === null
            ? 0.0
            : $transactions
                ->filter(fn (array $item): bool => $item['occurred_at']->lt($from))
                ->sum(fn (array $item): float => $item['signed_amount']);

        $periodTransactions = $transactions
            ->when($from !== null, fn (Collection $items): Collection => $items->filter(
                fn (array $item): bool => $item['occurred_at']->gte($from),
            ))
            ->when($to !== null, fn (Collection $items): Collection => $items->filter(
                fn (array $item): bool => $item['occurred_at']->lte($to),
            ))
            ->values();

        $runningBalance = round($openingBalance, 2);
        $entries = $periodTransactions->map(function (array $item) use (&$runningBalance): array {
            $runningBalance = round($runningBalance + $item['signed_amount'], 2);

            return [
                ...$item,
                'running_balance' => $runningBalance,
            ];
        });

        return [
            'supplier_id' => $supplierId,
            'from' => $from,
            'to' => $to,
            'opening_balance' => round($openingBalance, 2),
            'entries' => $entries,
            'debit_total' => round($entries->sum(fn (array $item): float => $item['debit']), 2),
            'credit_total' => round($entries->sum(fn (array $item): float => $item['credit']), 2),
            'closing_balance' => round($runningBalance, 2),
        ];
    }

    private function transactions(int $supplierId): Collection
    {
        $invoices = SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
                SupplierInvoiceStatus::PAID,
            ])
            ->get()
            ->flatMap(function (SupplierInvoice $invoice): Collection {
                $invoiceEntry = [[
                    'type' => 'invoice',
                    'reference' => $invoice->number,
                    'occurred_at' => $invoice->invoice_date,
                    'description' => 'Supplier invoice',
                    'debit' => (float) $invoice->grand_total,
                    'credit' => 0.0,
                    'signed_amount' => (float) $invoice->grand_total,
                    'supplier_invoice_id' => $invoice->id,
                    'supplier_payment_id' => null,
                    'supplier_adjustment_id' => null,
                ]];

                $payments = SupplierPayment::query()
                    ->where('supplier_invoice_id', $invoice->id)
                    ->get()
                    ->map(fn (SupplierPayment $payment): array => [
                        'type' => 'payment',
                        'reference' => $payment->payment_number,
                        'occurred_at' => $payment->paid_at,
                        'description' => 'Supplier payment',
                        'debit' => 0.0,
                        'credit' => (float) $payment->amount,
                        'signed_amount' => -((float) $payment->amount),
                        'supplier_invoice_id' => $invoice->id,
                        'supplier_payment_id' => $payment->id,
                        'supplier_adjustment_id' => null,
                    ]);

                $adjustments = $invoice->adjustments()
                    ->get()
                    ->map(function ($adjustment): array {
                        $isCredit = $adjustment->type === SupplierPayableAdjustmentType::CREDIT;
                        $amount = (float) $adjustment->amount;

                        return [
                            'type' => $adjustment->reversal_of_id !== null ? 'adjustment_reversal' : 'adjustment',
                            'reference' => $adjustment->number,
                            'occurred_at' => $adjustment->adjustment_date,
                            'description' => $adjustment->reason,
                            'debit' => $isCredit ? 0.0 : $amount,
                            'credit' => $isCredit ? $amount : 0.0,
                            'signed_amount' => $isCredit ? -$amount : $amount,
                            'supplier_invoice_id' => $adjustment->supplier_invoice_id,
                            'supplier_payment_id' => null,
                            'supplier_adjustment_id' => $adjustment->id,
                        ];
                    });

                return collect($invoiceEntry)->concat($payments)->concat($adjustments);
            })
            ->sortBy([
                ['occurred_at', 'asc'],
                ['type', 'asc'],
                ['reference', 'asc'],
            ])
            ->values();

        return $invoices;
    }
}
