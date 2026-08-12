<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sale_id',
        'monto',
        'metodo_pago',
        'fecha_pago',
        'notas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_pago' => 'datetime',
    ];

    /**
     * Cliente que realizó el pago/abono.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Venta asociada (puede ser null si es un abono a cuenta general).
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
