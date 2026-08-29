<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payable_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('number', 100)->unique();
            $table->string('type', 20);
            $table->date('adjustment_date');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['supplier_invoice_id', 'adjustment_date'],
                'payable_adjustment_invoice_date_index',
            );
            $table->index(
                ['type', 'adjustment_date'],
                'payable_adjustment_type_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payable_adjustments');
    }
};
