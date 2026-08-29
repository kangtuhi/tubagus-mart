<?php

namespace App\Enums;

enum SupplierPayableAdjustmentType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
}
