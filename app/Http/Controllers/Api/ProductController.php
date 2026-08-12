<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Listar productos con su stock total y lotes activos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->with(['lots' => function ($q) {
            $q->orderBy('id', 'desc');
        }])
        ->withSum('lots as stock_total', 'stock_actual')
        ->orderBy('nombre')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Crear un nuevo producto.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string|max:1000',
            'activo' => 'nullable|boolean',
        ]);

        $product = Product::create($validated);
        $product->stock_total = 0;
        $product->lots = [];

        return response()->json([
            'success' => true,
            'message' => 'Producto registrado correctamente.',
            'data' => $product,
        ], 201);
    }

    /**
     * Detalle del producto con sus lotes y compras.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with([
            'lots' => function ($q) {
                $q->orderByDesc('id');
            },
            'purchases' => function ($q) {
                $q->with('lot')->orderByDesc('fecha_compra')->limit(20);
            }
        ])
        ->withSum('lots as stock_total', 'stock_actual')
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Actualizar producto.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string|max:1000',
            'activo' => 'nullable|boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente.',
            'data' => $product,
        ]);
    }

    /**
     * Eliminar producto.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        if ($product->purchases()->exists() || $product->saleItems()->exists()) {
            // Si ya tiene historial, solo desactivar
            $product->update(['activo' => false]);
            return response()->json([
                'success' => true,
                'message' => 'El producto tiene transacciones registradas, se ha marcado como inactivo.',
                'data' => $product,
            ]);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente.',
        ]);
    }
}
