<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'legal_name',
    'tagline',
    'description',
    'phone',
    'email',
    'address_line_1',
    'address_line_2',
    'city',
    'province',
    'postal_code',
    'country',
    'timezone',
    'currency_code',
    'currency_locale',
    'tax_number',
    'logo_path',
])]
class BusinessProfile extends Model
{
}
