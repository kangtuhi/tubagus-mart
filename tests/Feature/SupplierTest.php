<?php

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('supplier can be created with commercial information', function () {
    $supplier = Supplier::factory()->withCreditLimit(50000000)->create();

    expect($supplier->code)->not->toBeEmpty()
        ->and($supplier->name)->not->toBeEmpty()
        ->and($supplier->payment_terms)->toBe('NET 30')
        ->and($supplier->credit_limit)->toBe('50000000.00')
        ->and($supplier->is_active)->toBeTrue();
});

test('supplier can be inactive', function () {
    $supplier = Supplier::factory()->inactive()->create();

    expect($supplier->is_active)->toBeFalse();
});

test('supplier code is unique', function () {
    $supplier = Supplier::factory()->create();

    expect(fn () => Supplier::factory()->create(['code' => $supplier->code]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
