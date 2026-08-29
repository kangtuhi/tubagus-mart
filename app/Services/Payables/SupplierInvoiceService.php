<?php

namespace App\Services\Payables;

use App\Enums\SupplierInvoiceStatus;
use App\Models\SupplierInvoice;
use DomainException;
use Illuminate\Database\DatabaseManager;

class SupplierInvoiceService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function post(SupplierInvoice $invoice): SupplierInvoice
    {
        return $this->database->transaction(function () use ($invoice) {
            $invoice->refresh();

            if ($invoice->status !== SupplierInvoiceStatus::DRAFT) {
                throw new DomainException('Only draft supplier invoices can be posted.');
            }

            $this->validateFinancials($invoice);

            if ((float) $invoice->paid_amount !== 0.0) {
                throw new DomainException('A draft supplier invoice cannot have a paid amount.');
            }

            $invoice->update(['status' => SupplierInvoiceStatus::POSTED]);

            return $invoice->refresh();
        });
    }

    public function void(SupplierInvoice $invoice): SupplierInvoice
    {
        return $this->database->transaction(function () use ($invoice) {
            $invoice->refresh();

            if ($invoice->status === SupplierInvoiceStatus::PAID) {
                throw new DomainException('Paid supplier invoices cannot be voided.');
            }

            if (! in_array($invoice->status, [
                SupplierInvoiceStatus::DRAFT,
                SupplierInvoiceStatus::POSTED,
                SupplierInvoiceStatus::PARTIALLY_PAID,
            ], true)) {
                throw new DomainException('This supplier invoice cannot be voided in its current status.');
            }

            $invoice->update(['status' => SupplierInvoiceStatus::VOID]);

            return $invoice->refresh();
        });
    }

    public function outstandingBalance(SupplierInvoice $invoice): float
    {
        return round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount), 2);
    }

    private function validateFinancials(SupplierInvoice $invoice): void
    {
        $subtotal = (float) $invoice->subtotal;
        $discount = (float) $invoice->discount_amount;
        $tax = (float) $invoice->tax_amount;
        $grandTotal = (float) $invoice->grand_total;

        if ($subtotal < 0 || $discount < 0 || $tax < 0 || $grandTotal < 0) {
            throw new DomainException('Supplier invoice financial amounts cannot be negative.');
        }

        $calculated = round($subtotal - $discount + $tax, 2);

        if ($discount > $subtotal) {
            throw new DomainException('Supplier invoice discount cannot exceed subtotal.');
        }

        if ($grandTotal !== $calculated) {
            throw new DomainException('Supplier invoice grand total does not match its financial components.');
        }
    }
}
