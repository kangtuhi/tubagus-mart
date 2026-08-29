<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayableAdjustment;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPayableAdjustmentService
{
    public function __construct(
        private readonly AccountingPeriodService $accountingPeriods,
    ) {}

    public function record(
        SupplierInvoice $invoice,
        SupplierPayableAdjustmentType $type,
        float $amount,
        string $number,
        string $reason,
        ?CarbonInterface $adjustmentDate = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): SupplierPayableAdjustment {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount must be greater than zero.',
            ]);
        }

        if (trim($number) === '') {
            throw ValidationException::withMessages([
                'number' => 'Adjustment number is required.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Adjustment reason is required.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $type, $amount, $number, $reason, $adjustmentDate, $notes, $createdBy): SupplierPayableAdjustment {
            /** @var SupplierInvoice $lockedInvoice */
            $lockedInvoice = SupplierInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            if (! in_array($lockedInvoice->status, [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
            ], true)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Only posted or partially paid invoices can receive adjustments.',
                ]);
            }

            if (SupplierPayableAdjustment::query()->where('number', $number)->exists()) {
                throw ValidationException::withMessages([
                    'number' => 'Adjustment number has already been used.',
                ]);
            }

            $adjustmentDate = $adjustmentDate ?? now();
            $this->accountingPeriods->assertOpenIfDefined($adjustmentDate);

            $balance = $this->balance($lockedInvoice);

            if ($type === SupplierPayableAdjustmentType::CREDIT && $amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Credit adjustment cannot exceed the invoice outstanding balance.',
                ]);
            }

            $adjustment = SupplierPayableAdjustment::query()->create([
                'supplier_invoice_id' => $lockedInvoice->getKey(),
                'number' => $number,
                'type' => $type,
                'adjustment_date' => $adjustmentDate,
                'amount' => $amount,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            $this->synchronizeInvoiceStatus($lockedInvoice);

            return $adjustment->refresh();
        });
    }

    public function reverse(
        SupplierPayableAdjustment $adjustment,
        string $reversalNumber,
        string $reason,
        ?int $reversedBy = null,
        ?CarbonInterface $reversalDate = null,
        ?string $notes = null,
    ): SupplierPayableAdjustment {
        if (trim($reversalNumber) === '') {
            throw ValidationException::withMessages([
                'number' => 'Reversal adjustment number is required.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Reversal reason is required.',
            ]);
        }

        return DB::transaction(function () use ($adjustment, $reversalNumber, $reason, $reversedBy, $reversalDate, $notes): SupplierPayableAdjustment {
            /** @var SupplierPayableAdjustment $lockedAdjustment */
            $lockedAdjustment = SupplierPayableAdjustment::query()
                ->lockForUpdate()
                ->findOrFail($adjustment->getKey());

            if ($lockedAdjustment->reversed_at !== null) {
                throw ValidationException::withMessages([
                    'adjustment' => 'This adjustment has already been reversed.',
                ]);
            }

            if ($lockedAdjustment->reversal_of_id !== null) {
                throw ValidationException::withMessages([
                    'adjustment' => 'A reversal adjustment cannot itself be reversed.',
                ]);
            }

            $invoice = SupplierInvoice::query()
                ->lockForUpdate()
                ->findOrFail($lockedAdjustment->supplier_invoice_id);

            if ($invoice->status === SupplierInvoiceStatus::VOID) {
                throw ValidationException::withMessages([
                    'invoice' => 'Adjustments cannot be reversed on a void invoice.',
                ]);
            }

            if (SupplierPayableAdjustment::query()->where('number', $reversalNumber)->exists()) {
                throw ValidationException::withMessages([
                    'number' => 'Reversal adjustment number has already been used.',
                ]);
            }

            $reversalDate = $reversalDate ?? now();
            $this->accountingPeriods->assertOpenIfDefined($reversalDate);

            $reversalType = $lockedAdjustment->type === SupplierPayableAdjustmentType::CREDIT
                ? SupplierPayableAdjustmentType::DEBIT
                : SupplierPayableAdjustmentType::CREDIT;

            if ($reversalType === SupplierPayableAdjustmentType::CREDIT && $lockedAdjustment->amount > $this->balance($invoice)) {
                throw ValidationException::withMessages([
                    'adjustment' => 'The adjustment cannot be reversed because the compensating credit would exceed the current outstanding balance.',
                ]);
            }

            $reversal = SupplierPayableAdjustment::query()->create([
                'supplier_invoice_id' => $invoice->getKey(),
                'number' => $reversalNumber,
                'type' => $reversalType,
                'adjustment_date' => $reversalDate,
                'amount' => $lockedAdjustment->amount,
                'reason' => 'Reversal of '.$lockedAdjustment->number.': '.$reason,
                'notes' => $notes,
                'created_by' => $reversedBy,
                'reversal_of_id' => $lockedAdjustment->getKey(),
            ]);

            $lockedAdjustment->update([
                'reversed_at' => $reversalDate,
                'reversed_by' => $reversedBy,
                'reversal_reason' => $reason,
            ]);

            $this->synchronizeInvoiceStatus($invoice);

            return $reversal->refresh();
        });
    }

    public function balance(SupplierInvoice $invoice): float
    {
        $credit = $invoice->adjustments()
            ->where('type', SupplierPayableAdjustmentType::CREDIT)
            ->sum('amount');
        $debit = $invoice->adjustments()
            ->where('type', SupplierPayableAdjustmentType::DEBIT)
            ->sum('amount');

        return round(max(
            0,
            (float) $invoice->grand_total + (float) $debit - (float) $credit - (float) $invoice->paid_amount,
        ), 2);
    }

    private function synchronizeInvoiceStatus(SupplierInvoice $invoice): void
    {
        $balance = $this->balance($invoice);
        $grandTotal = (float) $invoice->grand_total;

        $target = match (true) {
            $balance === 0.0 => SupplierInvoiceStatus::PAID,
            $balance < $grandTotal => SupplierInvoiceStatus::PARTIALLY_PAID,
            default => SupplierInvoiceStatus::POSTED,
        };

        if ($invoice->status !== $target) {
            $invoice->update(['status' => $target]);
        }
    }
}
