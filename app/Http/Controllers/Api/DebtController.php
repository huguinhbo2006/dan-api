<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    /**
     * Listado consolidado de clientes con adeudos activos y métricas globales de cuentas por cobrar.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::whereHas('sales', function ($q) {
            $q->where('estado', 'CON_ADEUDO');
        });

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('celular', 'like', "%{$search}%");
            });
        }

        $clientsWithDebt = $query->with(['sales' => function ($q) {
            $q->where('estado', 'CON_ADEUDO')
              ->orderBy('fecha_venta')
              ->with('items.product', 'items.lot');
        }])
        ->withSum(['sales as total_adeudado' => function ($q) {
            $q->where('estado', 'CON_ADEUDO');
        }], 'saldo_pendiente')
        ->orderByDesc('total_adeudado')
        ->get();

        // Métricas globales
        $totalGeneralPorCobrar = Sale::where('estado', 'CON_ADEUDO')->sum('saldo_pendiente');
        $totalClientesDeudores = $clientsWithDebt->count();
        $totalVentasConAdeudo = Sale::where('estado', 'CON_ADEUDO')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => [
                    'total_por_cobrar' => (float) $totalGeneralPorCobrar,
                    'clientes_deudores_count' => $totalClientesDeudores,
                    'ventas_pendientes_count' => $totalVentasConAdeudo,
                ],
                'clientes' => $clientsWithDebt,
            ],
        ]);
    }
}
