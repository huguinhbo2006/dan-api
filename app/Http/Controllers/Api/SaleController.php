<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Listar historial de ventas con filtros de estado y fecha.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['client', 'items.product', 'items.lot'])
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id');

        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }

        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        if ($fechaInicio = $request->input('fecha_inicio')) {
            $query->whereDate('fecha_venta', '>=', $fechaInicio);
        }

        if ($fechaFin = $request->input('fecha_fin')) {
            $query->whereDate('fecha_venta', '<=', $fechaFin);
        }

        $sales = $query->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    /**
     * Registrar una nueva venta (Punto de Venta / POS).
     * Descuenta stock de lotes, calcula utilidad por lote y genera adeudo si aplica.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'nullable|integer|exists:clients,id',
            'monto_pagado' => 'required|numeric|min:0',
            'metodo_pago' => 'nullable|string|max:50',
            'fecha_venta' => 'nullable|date',
            'notas' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.lot_id' => 'required|integer|exists:lots,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio_venta_unitario' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $clientId = $validated['client_id'] ?? null;
            $montoPagadoIngresado = (float) $validated['monto_pagado'];
            $itemsData = $validated['items'];

            $totalVenta = 0.0;
            $itemsToCreate = [];

            // 1. Validar stock disponible de cada lote y preparar partidas
            foreach ($itemsData as $itemInput) {
                $lot = Lot::with('product')->lockForUpdate()->findOrFail($itemInput['lot_id']);

                $cantidad = (int) $itemInput['cantidad'];
                if ($lot->stock_actual < $cantidad) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente en el producto '{$lot->product->nombre}' (Lote [{$lot->codigo_lote}]). Disponible: {$lot->stock_actual}, solicitado: {$cantidad}.",
                    ]);
                }

                // Descontar stock del lote
                $lot->stock_actual -= $cantidad;
                $lot->save();

                $precioVentaUnitario = isset($itemInput['precio_venta_unitario']) && $itemInput['precio_venta_unitario'] > 0
                    ? (float) $itemInput['precio_venta_unitario']
                    : (float) $lot->precio_venta;

                $precioCompraUnitario = (float) $lot->precio_compra;
                $subtotal = $precioVentaUnitario * $cantidad;
                $gananciaItem = ($precioVentaUnitario - $precioCompraUnitario) * $cantidad;

                $totalVenta += $subtotal;

                $itemsToCreate[] = [
                    'product_id' => $lot->product_id,
                    'lot_id' => $lot->id,
                    'cantidad' => $cantidad,
                    'precio_compra_unitario' => $precioCompraUnitario,
                    'precio_venta_unitario' => $precioVentaUnitario,
                    'subtotal' => $subtotal,
                    'ganancia_item' => $gananciaItem,
                ];
            }

            // 2. Determinar estado de la venta y saldo pendiente
            $totalVenta = round($totalVenta, 2);
            $montoEfectivoPagado = min($montoPagadoIngresado, $totalVenta);
            $saldoPendiente = round(max(0, $totalVenta - $montoEfectivoPagado), 2);

            if ($saldoPendiente > 0) {
                // Si hay saldo pendiente, debe haber un cliente asignado
                if (!$clientId) {
                    throw ValidationException::withMessages([
                        'client_id' => 'Para generar una venta a crédito o con adeudo es obligatorio seleccionar o registrar un cliente.',
                    ]);
                }
                $estado = 'CON_ADEUDO';
            } else {
                $estado = 'PAGADA';
            }

            // 3. Crear cabecera de la venta
            $sale = Sale::create([
                'client_id' => $clientId,
                'total' => $totalVenta,
                'monto_pagado' => $montoEfectivoPagado,
                'saldo_pendiente' => $saldoPendiente,
                'estado' => $estado,
                'fecha_venta' => $validated['fecha_venta'] ?? now(),
                'notas' => $validated['notas'] ?? null,
            ]);

            // 4. Crear los ítems de venta
            foreach ($itemsToCreate as $item) {
                $item['sale_id'] = $sale->id;
                SaleItem::create($item);
            }

            // 5. Registrar el pago inicial si se abonó dinero
            if ($montoEfectivoPagado > 0) {
                Payment::create([
                    'client_id' => $clientId,
                    'sale_id' => $sale->id,
                    'monto' => $montoEfectivoPagado,
                    'metodo_pago' => $validated['metodo_pago'] ?? 'efectivo',
                    'fecha_pago' => $validated['fecha_venta'] ?? now(),
                    'notas' => 'Pago inicial en punto de venta',
                ]);
            }

            $sale->load(['client', 'items.product', 'items.lot', 'payments']);

            return response()->json([
                'success' => true,
                'message' => $estado === 'PAGADA' 
                    ? 'Venta registrada y pagada exitosamente.' 
                    : "Venta registrada con adeudo de \${$saldoPendiente}.",
                'data' => $sale,
            ], 201);
        });
    }

    /**
     * Ver detalle completo de una venta.
     */
    public function show(int $id): JsonResponse
    {
        $sale = Sale::with(['client', 'items.product', 'items.lot', 'payments'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    /**
     * Cancelar una venta y devolver el stock a sus respectivos lotes.
     */
    public function cancel(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $sale = Sale::with('items')->findOrFail($id);

            if ($sale->estado === 'CANCELADA') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta ya se encuentra cancelada.',
                ], 422);
            }

            // Reintegrar stock a cada lote
            foreach ($sale->items as $item) {
                $lot = Lot::find($item->lot_id);
                if ($lot) {
                    $lot->stock_actual += $item->cantidad;
                    $lot->save();
                }
            }

            $sale->estado = 'CANCELADA';
            $sale->saldo_pendiente = 0;
            $sale->save();

            return response()->json([
                'success' => true,
                'message' => 'Venta cancelada e inventario restablecido en los lotes.',
                'data' => $sale,
            ]);
        });
    }
}
