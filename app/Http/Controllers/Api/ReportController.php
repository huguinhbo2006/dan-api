<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Reporte financiero completo de ganancias, utilidades por lote, desglose cobrado vs por cobrar y valor del inventario.
     */
    public function profits(Request $request): JsonResponse
    {
        $periodo = $request->input('periodo', 'mes'); // 'hoy', 'semana', 'mes', 'todos', 'personalizado'
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        // Determinar rango de fechas
        if ($periodo === 'hoy') {
            $fechaInicio = Carbon::today()->startOfDay();
            $fechaFin = Carbon::today()->endOfDay();
        } elseif ($periodo === 'semana') {
            $fechaInicio = Carbon::now()->startOfWeek();
            $fechaFin = Carbon::now()->endOfWeek();
        } elseif ($periodo === 'mes') {
            $fechaInicio = Carbon::now()->startOfMonth();
            $fechaFin = Carbon::now()->endOfMonth();
        } elseif ($periodo === 'personalizado' && $fechaInicio && $fechaFin) {
            $fechaInicio = Carbon::parse($fechaInicio)->startOfDay();
            $fechaFin = Carbon::parse($fechaFin)->endOfDay();
        } else {
            $fechaInicio = null;
            $fechaFin = null;
        }

        // 1. Query de ventas no canceladas
        $salesQuery = Sale::where('estado', '!=', 'CANCELADA');
        $paymentsQuery = Payment::query();
        $saleItemsQuery = SaleItem::whereHas('sale', function ($q) {
            $q->where('estado', '!=', 'CANCELADA');
        });

        if ($fechaInicio && $fechaFin) {
            $salesQuery->whereBetween('fecha_venta', [$fechaInicio, $fechaFin]);
            $paymentsQuery->whereBetween('fecha_pago', [$fechaInicio, $fechaFin]);
            $saleItemsQuery->whereHas('sale', function ($q) use ($fechaInicio, $fechaFin) {
                $q->whereBetween('fecha_venta', [$fechaInicio, $fechaFin]);
            });
        }

        // 2. Cálculos de Ventas y Utilidades
        $totalVentas = (float) $salesQuery->sum('total');
        $totalCobrado = (float) $salesQuery->sum('monto_pagado');
        $totalCuentasPorCobrar = (float) $salesQuery->sum('saldo_pendiente');
        $totalTransacciones = $salesQuery->count();

        $costoMercanciaVendida = (float) $saleItemsQuery->selectRaw('SUM(precio_compra_unitario * cantidad) as costo')->value('costo') ?? 0.0;
        $gananciaBrutaTotal = (float) $saleItemsQuery->sum('ganancia_item');

        // Margen de ganancia porcentual
        $margenPorcentual = $totalVentas > 0 ? round(($gananciaBrutaTotal / $totalVentas) * 100, 2) : 0.0;

        // Desglose: Ganancia ya cobrada en caja vs Ganancia pendiente en cuentas por cobrar
        $ratioGanancia = $totalVentas > 0 ? ($gananciaBrutaTotal / $totalVentas) : 0.0;
        $gananciaCobradaRealizada = round($totalCobrado * $ratioGanancia, 2);
        $gananciaPendientePorCobrar = round($totalCuentasPorCobrar * $ratioGanancia, 2);

        // 3. Valoración del Inventario Actual (en Lotes activos)
        $activeLots = Lot::where('stock_actual', '>', 0)->get();
        $valorInventarioCosto = (float) $activeLots->sum(fn ($l) => $l->stock_actual * (float) $l->precio_compra);
        $valorInventarioVenta = (float) $activeLots->sum(fn ($l) => $l->stock_actual * (float) $l->precio_venta);
        $gananciaPotencialInventario = round($valorInventarioVenta - $valorInventarioCosto, 2);
        $articulosEnStock = (int) $activeLots->sum('stock_actual');

        // 4. Top 10 Productos Más Vendidos y Rentables
        $topProductos = SaleItem::whereHas('sale', function ($q) use ($fechaInicio, $fechaFin) {
            $q->where('estado', '!=', 'CANCELADA');
            if ($fechaInicio && $fechaFin) {
                $q->whereBetween('fecha_venta', [$fechaInicio, $fechaFin]);
            }
        })
        ->select(
            'product_id',
            DB::raw('SUM(cantidad) as total_unidades'),
            DB::raw('SUM(subtotal) as total_ingreso'),
            DB::raw('SUM(ganancia_item) as total_ganancia')
        )
        ->groupBy('product_id')
        ->with('product')
        ->orderByDesc('total_ganancia')
        ->limit(10)
        ->get()
        ->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'nombre' => $item->product ? $item->product->nombre : 'Desconocido',
                'codigo' => $item->product ? $item->product->codigo : null,
                'unidades_vendidas' => (int) $item->total_unidades,
                'ingreso_total' => (float) $item->total_ingreso,
                'ganancia_neta' => (float) $item->total_ganancia,
            ];
        });

        // 5. Rentabilidad por Lote Vendido
        $lotesVendidos = SaleItem::whereHas('sale', function ($q) use ($fechaInicio, $fechaFin) {
            $q->where('estado', '!=', 'CANCELADA');
            if ($fechaInicio && $fechaFin) {
                $q->whereBetween('fecha_venta', [$fechaInicio, $fechaFin]);
            }
        })
        ->select(
            'lot_id',
            'product_id',
            'precio_compra_unitario',
            'precio_venta_unitario',
            DB::raw('SUM(cantidad) as total_unidades'),
            DB::raw('SUM(subtotal) as total_ingreso'),
            DB::raw('SUM(ganancia_item) as total_ganancia')
        )
        ->groupBy('lot_id', 'product_id', 'precio_compra_unitario', 'precio_venta_unitario')
        ->with(['lot', 'product'])
        ->orderByDesc('total_ganancia')
        ->get()
        ->map(function ($item) {
            return [
                'lot_id' => $item->lot_id,
                'codigo_lote' => $item->lot ? $item->lot->codigo_lote : 'N/A',
                'product_nombre' => $item->product ? $item->product->nombre : 'Desconocido',
                'precio_compra' => (float) $item->precio_compra_unitario,
                'precio_venta' => (float) $item->precio_venta_unitario,
                'unidades_vendidas' => (int) $item->total_unidades,
                'total_ingreso' => (float) $item->total_ingreso,
                'total_ganancia' => (float) $item->total_ganancia,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'periodo' => [
                    'nombre' => $periodo,
                    'fecha_inicio' => $fechaInicio ? $fechaInicio->toDateTimeString() : null,
                    'fecha_fin' => $fechaFin ? $fechaFin->toDateTimeString() : null,
                ],
                'resumen_financiero' => [
                    'total_ventas' => $totalVentas,
                    'costo_mercancia_vendida' => $costoMercanciaVendida,
                    'ganancia_bruta_total' => $gananciaBrutaTotal,
                    'margen_porcentual' => $margenPorcentual,
                    'total_cobrado_efectivo' => $totalCobrado,
                    'total_cuentas_por_cobrar' => $totalCuentasPorCobrar,
                    'ganancia_cobrada_realizada' => $gananciaCobradaRealizada,
                    'ganancia_pendiente_por_cobrar' => $gananciaPendientePorCobrar,
                    'total_transacciones' => $totalTransacciones,
                ],
                'inventario_actual' => [
                    'valor_en_costo' => $valorInventarioCosto,
                    'valor_en_venta' => $valorInventarioVenta,
                    'ganancia_potencial' => $gananciaPotencialInventario,
                    'total_articulos' => $articulosEnStock,
                    'total_lotes_activos' => $activeLots->count(),
                ],
                'top_productos' => $topProductos,
                'rendimiento_lotes' => $lotesVendidos,
            ],
        ]);
    }
}
