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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->decimal('total', 10, 2);
            $table->decimal('monto_pagado', 10, 2)->default(0);
            $table->decimal('saldo_pendiente', 10, 2)->default(0);
            $table->string('estado', 20)->default('PAGADA')->index(); // 'PAGADA', 'CON_ADEUDO', 'CANCELADA'
            $table->dateTime('fecha_venta')->useCurrent();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
