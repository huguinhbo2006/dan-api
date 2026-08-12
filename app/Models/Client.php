<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'celular',
        'notas',
    ];

    /**
     * Ventas asociadas al cliente.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Abonos realizados por el cliente.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Saldo total adeudado por el cliente en ventas pendientes.
     */
    public function getSaldoDeudorAttribute(): float
    {
        return (float) $this->sales()
            ->where('estado', 'CON_ADEUDO')
            ->sum('saldo_pendiente');
    }
}
