<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'lot_id',
        'cantidad',
        'precio_compra_unitario',
        'precio_venta_unitario',
        'subtotal',
        'ganancia_item',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_compra_unitario' => 'decimal:2',
        'precio_venta_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'ganancia_item' => 'decimal:2',
    ];

    /**
     * Venta a la que pertenece el ítem.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Producto vendido.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Lote específico del que se descontó el stock.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
