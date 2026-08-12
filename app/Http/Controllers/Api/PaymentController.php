<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * Listar historial de abonos y pagos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['client', 'sale'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id');

        if ($clientId = $request->input('client_id')) {
            $query->where('client_id', $clientId);
        }

        if ($saleId = $request->input('sale_id')) {
            $query->where('sale_id', $saleId);
        }

        $payments = $query->paginate($request->integer('per_page', 30));

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Registrar un abono a un cliente o a una venta específica.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'monto' => 'required|numeric|min:0.01',
            'sale_id' => 'nullable|integer|exists:sales,id',
            'metodo_pago' => 'nullable|string|max:50',
            'fecha_pago' => 'nullable|date',
            'notas' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated) {
            $client = Client::findOrFail($validated['client_id']);
            $montoTotalAbono = (float) $validated['monto'];
            $montoRestante = $montoTotalAbono;
            $saleId = $validated['sale_id'] ?? null;
            $fechaPago = $validated['fecha_pago'] ?? now();
            $metodoPago = $validated['metodo_pago'] ?? 'efectivo';
            $notas = $validated['notas'] ?? null;

            $ventasImpactadas = [];

            if ($saleId) {
                // Abono dirigido a una venta específica
                $sale = Sale::where('client_id', $client->id)
                    ->where('id', $saleId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($sale->saldo_pendiente <= 0) {
                    throw ValidationException::withMessages([
                        'sale_id' => 'Esta venta ya se encuentra totalmente liquidada.',
                    ]);
                }

                $montoAplicar = min($montoRestante, (float) $sale->saldo_pendiente);
                $sale->saldo_pendiente = round($sale->saldo_pendiente - $montoAplicar, 2);
                $sale->monto_pagado = round($sale->monto_pagado + $montoAplicar, 2);

                if ($sale->saldo_pendiente <= 0) {
                    $sale->estado = 'PAGADA';
                    $sale->saldo_pendiente = 0;
                }
                $sale->save();

                $ventasImpactadas[] = [
                    'sale_id' => $sale->id,
                    'monto_aplicado' => $montoAplicar,
                    'nuevo_saldo' => (float) $sale->saldo_pendiente,
                    'estado' => $sale->estado,
                ];

                $montoRestante -= $montoAplicar;
            } else {
                // Distribución automática FIFO a las ventas más antiguas con adeudo
                $pendingSales = Sale::where('client_id', $client->id)
                    ->where('estado', 'CON_ADEUDO')
                    ->orderBy('fecha_venta')
                    ->lockForUpdate()
                    ->get();

                if ($pendingSales->isEmpty()) {
                    throw ValidationException::withMessages([
                        'client_id' => 'El cliente no tiene ventas con adeudos pendientes.',
                    ]);
                }

                foreach ($pendingSales as $sale) {
                    if ($montoRestante <= 0) {
                        break;
                    }

                    $montoAplicar = min($montoRestante, (float) $sale->saldo_pendiente);
                    $sale->saldo_pendiente = round($sale->saldo_pendiente - $montoAplicar, 2);
                    $sale->monto_pagado = round($sale->monto_pagado + $montoAplicar, 2);

                    if ($sale->saldo_pendiente <= 0) {
                        $sale->estado = 'PAGADA';
                        $sale->saldo_pendiente = 0;
                    }
                    $sale->save();

                    $ventasImpactadas[] = [
                        'sale_id' => $sale->id,
                        'monto_aplicado' => $montoAplicar,
                        'nuevo_saldo' => (float) $sale->saldo_pendiente,
                        'estado' => $sale->estado,
                    ];

                    $montoRestante -= $montoAplicar;
                }
            }

            // Registrar el abono
            $payment = Payment::create([
                'client_id' => $client->id,
                'sale_id' => $saleId,
                'monto' => $montoTotalAbono,
                'metodo_pago' => $metodoPago,
                'fecha_pago' => $fechaPago,
                'notas' => $notas,
            ]);

            // Obtener el nuevo saldo total adeudado del cliente
            $nuevoSaldoTotal = (float) Sale::where('client_id', $client->id)
                ->where('estado', 'CON_ADEUDO')
                ->sum('saldo_pendiente');

            return response()->json([
                'success' => true,
                'message' => "Abono de \${$montoTotalAbono} registrado correctamente.",
                'data' => [
                    'payment' => $payment,
                    'ventas_impactadas' => $ventasImpactadas,
                    'nuevo_saldo_total_cliente' => $nuevoSaldoTotal,
                ],
            ], 201);
        });
    }
}
