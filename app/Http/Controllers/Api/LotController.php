<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Services\LotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function __construct(
        protected LotService $lotService
    ) {}

    /**
     * Listar lotes disponibles (con stock > 0) optimizados para el selector de venta (POS) e inventario.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lot::with('product')
            ->whereHas('product', function ($q) {
                $q->where('activo', true);
            });

        // Filtrar por stock disponible por defecto
        if (!$request->boolean('include_empty', false)) {
            $query->where('stock_actual', '>', 0);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('codigo_lote', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('nombre', 'like', "%{$search}%")
                         ->orWhere('codigo', 'like', "%{$search}%");
                  });
            });
        }

        $lots = $query->orderBy('product_id')->orderBy('id', 'desc')->get();

        // Mapear con label formateado listo para selectors de UI
        $mapped = $lots->map(function (Lot $lot) {
            return [
                'id' => $lot->id,
                'product_id' => $lot->product_id,
                'product_nombre' => $lot->product->nombre,
                'product_codigo' => $lot->product->codigo,
                'codigo_lote' => $lot->codigo_lote,
                'precio_compra' => (float) $lot->precio_compra,
                'precio_venta' => (float) $lot->precio_venta,
                'stock_actual' => (int) $lot->stock_actual,
                'margen_unitario' => (float) $lot->margen_unitario,
                'display_label' => "{$lot->product->nombre} - Lote [{$lot->codigo_lote}] (Disp: {$lot->stock_actual} | \${$lot->precio_venta})",
                'created_at' => $lot->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mapped,
        ]);
    }

    /**
     * Listar todos los lotes de un producto en específico.
     */
    public function byProduct(int $productId): JsonResponse
    {
        $lots = Lot::where('product_id', $productId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lots,
        ]);
    }

    /**
     * Endpoint para previsualizar si una compra reutilizará un lote o creará uno nuevo.
     */
    public function previewMatch(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'precio_compra' => 'required|numeric|min:0.01',
        ]);

        $preview = $this->lotService->previewLotMatch(
            (int) $request->input('product_id'),
            (float) $request->input('precio_compra')
        );

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }
}
