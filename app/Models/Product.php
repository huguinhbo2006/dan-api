<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Lotes creados para este producto.
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Lotes con stock disponible > 0.
     */
    public function activeLots(): HasMany
    {
        return $this->hasMany(Lot::class)->where('stock_actual', '>', 0);
    }

    /**
     * Historial de compras asociadas al producto.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Partidas de venta de este producto.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Stock global sumando todos sus lotes.
     */
    public function getStockTotalAttribute(): int
    {
        return (int) $this->lots()->sum('stock_actual');
    }
}
