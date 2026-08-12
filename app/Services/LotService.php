<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Product;
use Illuminate\Support\Str;

class LotService
{
    /**
     * Caracteres permitidos para generar el lote (alfanuméricos legibles).
     */
    protected const LOT_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Genera un código de lote de 4 caracteres alfanuméricos aleatorio.
     */
    public function generateLotCode(int $productId): string
    {
        $maxAttempts = 50;
        $attempt = 0;

        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= self::LOT_CHARSET[random_int(0, strlen(self::LOT_CHARSET) - 1)];
            }

            // Verificar que no exista ya un lote con este código para el producto
            $exists = Lot::where('product_id', $productId)
                ->where('codigo_lote', $code)
                ->exists();

            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        return $code;
    }

    /**
     * Busca si ya existe un lote previo con exactamente el mismo precio de compra
     * o crea uno nuevo de 4 caracteres si el precio es distinto.
     *
     * @return array{lot: Lot, is_new: bool, action_description: string}
     */
    public function findOrCreateLot(int $productId, float $precioCompra, float $precioVenta, int $cantidad): array
    {
        $precioCompraFormatted = number_format($precioCompra, 2, '.', '');
        $precioVentaFormatted = number_format($precioVenta, 2, '.', '');

        // Buscar si existe un lote previo para este producto con el mismo precio de compra
        $existingLot = Lot::where('product_id', $productId)
            ->where('precio_compra', $precioCompraFormatted)
            ->first();

        if ($existingLot) {
            // Reutilizar el lote: sumar cantidad comprada y actualizar precio de venta actual
            $existingLot->stock_actual += $cantidad;
            $existingLot->precio_venta = $precioVentaFormatted;
            $existingLot->save();

            return [
                'lot' => $existingLot,
                'is_new' => false,
                'action_description' => "Se reutilizó el Lote {$existingLot->codigo_lote} (mismo precio de compra: \${$precioCompraFormatted}). Nuevo stock: {$existingLot->stock_actual}.",
            ];
        }

        // Generar un nuevo código de 4 caracteres
        $nuevoCodigo = $this->generateLotCode($productId);

        $newLot = Lot::create([
            'product_id' => $productId,
            'codigo_lote' => $nuevoCodigo,
            'precio_compra' => $precioCompraFormatted,
            'precio_venta' => $precioVentaFormatted,
            'stock_actual' => $cantidad,
        ]);

        return [
            'lot' => $newLot,
            'is_new' => true,
            'action_description' => "Se generó un nuevo Lote: {$nuevoCodigo} con precio de compra \${$precioCompraFormatted}.",
        ];
    }

    /**
     * Previsualiza si una compra dada reutilizará un lote o creará uno nuevo.
     * Útil para que la app móvil le muestre al usuario en tiempo real antes de guardar.
     */
    public function previewLotMatch(int $productId, float $precioCompra): array
    {
        $precioCompraFormatted = number_format($precioCompra, 2, '.', '');

        $existingLot = Lot::where('product_id', $productId)
            ->where('precio_compra', $precioCompraFormatted)
            ->first();

        if ($existingLot) {
            return [
                'matches_existing' => true,
                'codigo_lote' => $existingLot->codigo_lote,
                'lot_id' => $existingLot->id,
                'precio_compra' => (float) $existingLot->precio_compra,
                'precio_venta_sugerido' => (float) $existingLot->precio_venta,
                'stock_actual' => $existingLot->stock_actual,
                'mensaje' => "Se reutilizará el Lote {$existingLot->codigo_lote} (Costo actual: \${$precioCompraFormatted}).",
            ];
        }

        return [
            'matches_existing' => false,
            'codigo_lote' => null,
            'lot_id' => null,
            'precio_compra' => $precioCompra,
            'precio_venta_sugerido' => null,
            'stock_actual' => 0,
            'mensaje' => 'Se generará automáticamente un nuevo código de lote de 4 caracteres.',
        ];
    }
}
