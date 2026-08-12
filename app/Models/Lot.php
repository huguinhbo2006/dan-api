<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'codigo_lote',
        'precio_compra',
        'precio_venta',
        'stock_actual',
    ];

    protected $casts = [
        'precio_compra' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'stock_actual' => 'integer',
    ];

    /**
     * Producto al que pertenece este lote.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Compras registradas en este lote.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Partidas de venta descontadas de este lote.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Margen de ganancia unitario esperado.
     */
    public function getMargenUnitarioAttribute(): float
    {
        return (float) ($this->precio_venta - $this->precio_compra);
    }
}
