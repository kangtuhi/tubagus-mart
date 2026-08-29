<?php

namespace App\Enums;

enum SupplierInvoiceStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case VOID = 'void';
}
