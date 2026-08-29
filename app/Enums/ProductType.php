<?php

namespace App\Enums;

enum ProductType: string
{
    case STOCK = 'stock';
    case NON_STOCK = 'non_stock';
    case SERVICE = 'service';
}
