<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Listar clientes con búsqueda y saldo deudor acumulado.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('celular', 'like', "%{$search}%");
            });
        }

        $clients = $query->withSum(['sales as total_adeudado' => function ($q) {
            $q->where('estado', 'CON_ADEUDO');
        }], 'saldo_pendiente')
        ->withCount(['sales as ventas_con_adeudo_count' => function ($q) {
            $q->where('estado', 'CON_ADEUDO');
        }])
        ->orderBy('nombre')
        ->get();

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Crear un nuevo cliente.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'celular' => 'required|string|max:30',
            'notas' => 'nullable|string|max:1000',
        ]);

        $client = Client::create($validated);
        $client->total_adeudado = 0;
        $client->ventas_con_adeudo_count = 0;

        return response()->json([
            'success' => true,
            'message' => 'Cliente registrado correctamente.',
            'data' => $client,
        ], 201);
    }

    /**
     * Detalle del cliente con su resumen de cuenta.
     */
    public function show(int $id): JsonResponse
    {
        $client = Client::with([
            'sales' => function ($q) {
                $q->orderByDesc('fecha_venta')->with('items.product', 'items.lot');
            },
            'payments' => function ($q) {
                $q->orderByDesc('fecha_pago');
            }
        ])->findOrFail($id);

        $totalAdeudado = $client->sales->where('estado', 'CON_ADEUDO')->sum('saldo_pendiente');

        return response()->json([
            'success' => true,
            'data' => array_merge($client->toArray(), [
                'total_adeudado' => (float) $totalAdeudado,
            ]),
        ]);
    }

    /**
     * Actualizar datos del cliente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'celular' => 'required|string|max:30',
            'notas' => 'nullable|string|max:1000',
        ]);

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente.',
            'data' => $client,
        ]);
    }

    /**
     * Eliminar cliente.
     */
    public function destroy(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        if ($client->sales()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el cliente porque tiene ventas o adeudos registrados.',
            ], 422);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    /**
     * Estado de cuenta detallado con ventas a crédito y abonos.
     */
    public function accountStatus(int $id): JsonResponse
    {
        $client = Client::findOrFail($id);

        $unpaidSales = $client->sales()
            ->where('estado', 'CON_ADEUDO')
            ->orderBy('fecha_venta')
            ->with('items.product', 'items.lot')
            ->get();

        $payments = $client->payments()
            ->orderByDesc('fecha_pago')
            ->get();

        $totalDeuda = $unpaidSales->sum('saldo_pendiente');

        return response()->json([
            'success' => true,
            'data' => [
                'cliente' => $client,
                'total_adeudado' => (float) $totalDeuda,
                'ventas_pendientes' => $unpaidSales,
                'historial_abonos' => $payments,
            ],
        ]);
    }
}
