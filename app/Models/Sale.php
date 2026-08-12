<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'total',
        'monto_pagado',
        'saldo_pendiente',
        'estado',
        'fecha_venta',
        'notas',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'fecha_venta' => 'datetime',
    ];

    /**
     * Cliente asociado (puede ser null para venta mostrador anónima).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Partidas o productos incluidos en la venta.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Pagos o abonos aplicados a esta venta.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Ganancia total de la venta sumando la ganancia de cada ítem.
     */
    public function getGananciaTotalAttribute(): float
    {
        return (float) $this->items()->sum('ganancia_item');
    }
}
