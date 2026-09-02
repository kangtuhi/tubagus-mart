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
            $table->foreignId('accounting_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 30)->default('open');
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->timestamp('detected_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

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
