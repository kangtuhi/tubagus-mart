<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case PURCHASE = 'purchase';
    case PURCHASE_RETURN = 'purchase_return';
    case SALE = 'sale';
    case SALE_RETURN = 'sale_return';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
}
