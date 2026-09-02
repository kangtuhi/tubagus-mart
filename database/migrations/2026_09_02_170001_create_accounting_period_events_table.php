<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_period_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accounting_period_id')
                ->constrained('accounting_periods')
                ->restrictOnDelete();
            $table->string('action', 30);
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('performed_at');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['accounting_period_id', 'performed_at']);
            $table->index(['action', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_events');
    }
};
