<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payable_adjustments', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->unique()
                ->after('created_by')
                ->constrained('supplier_payable_adjustments')
                ->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
            $table->foreignId('reversed_by')
                ->nullable()
                ->after('reversed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reversal_reason')->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payable_adjustments', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropColumn([
                'reversal_of_id',
                'reversed_at',
                'reversed_by',
                'reversal_reason',
            ]);
        });
    }
};
