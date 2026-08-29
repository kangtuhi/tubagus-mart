<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->timestamps();

            $table->unique(
                ['goods_receipt_id', 'purchase_order_item_id'],
                'goods_receipt_item_receipt_po_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
