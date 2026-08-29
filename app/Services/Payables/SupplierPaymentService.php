<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\DatabaseManager;

class SupplierPaymentService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AccountingPeriodService $accountingPeriods,
    ) {}

    public function record(
        SupplierInvoice $invoice,
        string $paymentNumber,
        float $amount,
        ?int $paidBy = null,
        ?string $reference = null,
        ?string $notes = null,
        ?CarbonInterface $paidAt = null,
    ): SupplierPayment {
        return $this->database->transaction(function () use ($invoice, $paymentNumber, $amount, $paidBy, $reference, $notes, $paidAt) {
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! in_array($invoice->status, [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
            ], true)) {
                throw new DomainException('Only posted or partially paid supplier invoices can receive payments.');
            }

            if ($amount <= 0) {
                throw new DomainException('Payment amount must be greater than zero.');
            }

            $remaining = round((float) $invoice->grand_total - (float) $invoice->paid_amount, 2);

            if ($amount > $remaining) {
                throw new DomainException('Payment amount cannot exceed the outstanding supplier invoice balance.');
            }

            $paymentDate = $paidAt ?? now();

            $this->accountingPeriods->assertOpenIfDefined($paymentDate);

            $payment = $invoice->payments()->create([
                'payment_number' => $paymentNumber,
                'paid_at' => $paymentDate,
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes,
                'paid_by' => $paidBy,
            ]);

            $paidAmount = round((float) $invoice->paid_amount + $amount, 2);

            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= (float) $invoice->grand_total
                    ? SupplierInvoiceStatus::PAID
                    : SupplierInvoiceStatus::PARTIALLY_PAID,
            ]);

            return $payment->refresh();
        });
    }

    public function outstandingBalance(SupplierInvoice $invoice): float
    {
        return round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount), 2);
    }
}
