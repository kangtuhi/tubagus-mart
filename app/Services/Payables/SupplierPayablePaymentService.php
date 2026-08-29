<?php

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPayablePaymentService
{
    /**
     * Record a payment against a supplier invoice atomically.
     *
     * The invoice row is locked for the duration of the transaction so
     * concurrent payments cannot consume the same outstanding balance.
     */
    public function record(
        SupplierInvoice $invoice,
        float $amount,
        string $paymentNumber,
        ?CarbonInterface $paidAt = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $paidBy = null,
    ): SupplierPayment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if (trim($paymentNumber) === '') {
            throw ValidationException::withMessages([
                'payment_number' => 'Payment number is required.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $amount, $paymentNumber, $paidAt, $reference, $notes, $paidBy): SupplierPayment {
            /** @var SupplierInvoice $lockedInvoice */
            $lockedInvoice = SupplierInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            if (! in_array($lockedInvoice->status, [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
            ], true)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Only posted or partially paid invoices can receive payments.',
                ]);
            }

            if (SupplierPayment::query()->where('payment_number', $paymentNumber)->exists()) {
                throw ValidationException::withMessages([
                    'payment_number' => 'Payment number has already been used.',
                ]);
            }

            $grandTotal = round((float) $lockedInvoice->grand_total, 2);
            $paidAmount = round((float) $lockedInvoice->paid_amount, 2);
            $outstanding = round($grandTotal - $paidAmount, 2);

            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice has no outstanding balance.',
                ]);
            }

            if ($amount > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the invoice outstanding balance.',
                ]);
            }

            $newPaidAmount = round($paidAmount + $amount, 2);
            $newStatus = $newPaidAmount >= $grandTotal
                ? SupplierInvoiceStatus::PAID
                : SupplierInvoiceStatus::PARTIALLY_PAID;

            $payment = SupplierPayment::query()->create([
                'supplier_invoice_id' => $lockedInvoice->getKey(),
                'payment_number' => $paymentNumber,
                'paid_at' => $paidAt ?? now(),
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes,
                'paid_by' => $paidBy,
            ]);

            $lockedInvoice->update([
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
            ]);

            return $payment;
        });
    }
}
