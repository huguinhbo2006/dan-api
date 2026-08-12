<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'lot_id',
        'cantidad',
        'precio_compra',
        'precio_venta',
        'fecha_compra',
        'notas',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'fecha_compra' => 'datetime',
    ];

    /**
     * Producto comprado.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Lote asignado o reutilizado en la compra.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Monto total invertido en la compra.
     */
    public function getTotalCompraAttribute(): float
    {
        return (float) ($this->cantidad * $this->precio_compra);
    }
}
