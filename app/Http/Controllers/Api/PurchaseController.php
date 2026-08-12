<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\LotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function __construct(
        protected LotService $lotService
    ) {}

    /**
     * Listar historial de compras realizadas.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['product', 'lot'])
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id');

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($lotId = $request->input('lot_id')) {
            $query->where('lot_id', $lotId);
        }

        $purchases = $query->get()->map(function (Purchase $p) {
            return [
                'id' => $p->id,
                'product_id' => $p->product_id,
                'product_nombre' => $p->product ? $p->product->nombre : 'Desconocido',
                'lot_id' => $p->lot_id,
                'codigo_lote' => $p->lot ? $p->lot->codigo_lote : 'N/A',
                'cantidad' => (int) $p->cantidad,
                'precio_compra' => (float) $p->precio_compra,
                'precio_venta' => (float) $p->precio_venta,
                'total_compra' => (float) $p->total_compra,
                'fecha_compra' => $p->fecha_compra,
                'notas' => $p->notas,
                'created_at' => $p->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    /**
     * Registrar una nueva compra aplicando la regla de asignación/reutilización de lote de 4 caracteres.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'cantidad' => 'required|integer|min:1',
            'precio_compra' => 'required|numeric|min:0.01',
            'precio_venta' => 'required|numeric|min:0.01',
            'fecha_compra' => 'nullable|date',
            'notas' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated) {
            // Aplicar la lógica de negocio para buscar lote con mismo precio o generar nuevo
            $lotResult = $this->lotService->findOrCreateLot(
                (int) $validated['product_id'],
                (float) $validated['precio_compra'],
                (float) $validated['precio_venta'],
                (int) $validated['cantidad']
            );

            $lot = $lotResult['lot'];

            // Registrar la compra
            $purchase = Purchase::create([
                'product_id' => $validated['product_id'],
                'lot_id' => $lot->id,
                'cantidad' => $validated['cantidad'],
                'precio_compra' => $validated['precio_compra'],
                'precio_venta' => $validated['precio_venta'],
                'fecha_compra' => $validated['fecha_compra'] ?? now(),
                'notas' => $validated['notas'] ?? null,
            ]);

            $purchase->load(['product', 'lot']);

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada exitosamente. ' . $lotResult['action_description'],
                'data' => [
                    'purchase' => $purchase,
                    'lot' => $lot,
                    'is_new_lot' => $lotResult['is_new'],
                    'action_description' => $lotResult['action_description'],
                ],
            ], 201);
        });
    }
}
