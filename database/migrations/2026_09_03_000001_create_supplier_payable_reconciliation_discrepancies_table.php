<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payable_reconciliation_discrepancies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('accounting_period_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('type', 40);
            $table->string('status', 30)->default('open');
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->timestamp('detected_at');
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('accounting_period_id', 'sprd_period_fk')
                ->references('id')
                ->on('accounting_periods')
                ->cascadeOnDelete();
            $table->foreign('supplier_id', 'sprd_supplier_fk')
                ->references('id')
                ->on('suppliers')
                ->cascadeOnDelete();
            $table->foreign('opened_by', 'sprd_opened_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('resolved_by', 'sprd_resolved_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['accounting_period_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payable_reconciliation_discrepancies');
    }
};
