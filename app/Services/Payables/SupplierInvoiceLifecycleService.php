<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceLifecycleService
{
    public function __construct(
        private readonly AccountingPeriodService $accountingPeriods,
    ) {}

    /**
     * Transition a supplier invoice through an explicitly allowed state change.
     */
    public function transition(SupplierInvoice $invoice, SupplierInvoiceStatus $to): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $to): SupplierInvoice {
            /** @var SupplierInvoice $lockedInvoice */
            $lockedInvoice = SupplierInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());

            $from = $lockedInvoice->status;

            if (! $this->canTransition($from, $to)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Invalid supplier invoice status transition from [%s] to [%s].',
                        $from->value,
                        $to->value,
                    ),
                ]);
            }

            if ($to === SupplierInvoiceStatus::VOID && $lockedInvoice->payments()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'An invoice with recorded payments cannot be voided.',
                ]);
            }

            if ($to === SupplierInvoiceStatus::POSTED) {
                $this->accountingPeriods->assertOpenIfDefined($lockedInvoice->invoice_date);
            }

            $lockedInvoice->update(['status' => $to]);

            return $lockedInvoice->refresh();
        });
    }

    public function canTransition(
        SupplierInvoiceStatus $from,
        SupplierInvoiceStatus $to,
    ): bool {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            SupplierInvoiceStatus::DRAFT => in_array($to, [
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::VOID,
            ], true),
            SupplierInvoiceStatus::POSTED => in_array($to, [
                SupplierInvoiceStatus::PARTIALLY_PAID,
                SupplierInvoiceStatus::PAID,
                SupplierInvoiceStatus::VOID,
            ], true),
            SupplierInvoiceStatus::PARTIALLY_PAID => $to === SupplierInvoiceStatus::PAID,
            SupplierInvoiceStatus::PAID,
            SupplierInvoiceStatus::VOID => false,
        };
    }

    public function post(SupplierInvoice $invoice): SupplierInvoice
    {
        return $this->transition($invoice, SupplierInvoiceStatus::POSTED);
    }

    public function void(SupplierInvoice $invoice): SupplierInvoice
    {
        return $this->transition($invoice, SupplierInvoiceStatus::VOID);
    }
}
