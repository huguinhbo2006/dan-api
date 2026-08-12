<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('lot_id')->constrained('lots');
            $table->integer('cantidad');
            $table->decimal('precio_compra_unitario', 10, 2); // Costo histórico al momento de la venta
            $table->decimal('precio_venta_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('ganancia_item', 10, 2); // (precio_venta_unitario - precio_compra_unitario) * cantidad
            $table->timestamps();

            $table->index(['sale_id', 'lot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
