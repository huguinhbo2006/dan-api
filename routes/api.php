<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Tiendani
|--------------------------------------------------------------------------
*/

// Clientes
Route::get('/clientes', [ClientController::class, 'index']);
Route::post('/clientes', [ClientController::class, 'store']);
Route::get('/clientes/{id}', [ClientController::class, 'show']);
Route::put('/clientes/{id}', [ClientController::class, 'update']);
Route::delete('/clientes/{id}', [ClientController::class, 'destroy']);
Route::get('/clientes/{id}/estado-cuenta', [ClientController::class, 'accountStatus']);

// Productos
Route::get('/productos', [ProductController::class, 'index']);
Route::post('/productos', [ProductController::class, 'store']);
Route::get('/productos/{id}', [ProductController::class, 'show']);
Route::put('/productos/{id}', [ProductController::class, 'update']);
Route::delete('/productos/{id}', [ProductController::class, 'destroy']);

// Lotes e Inventario
Route::get('/lotes', [LotController::class, 'index']);
Route::get('/lotes/producto/{productId}', [LotController::class, 'byProduct']);
Route::post('/lotes/preview-match', [LotController::class, 'previewMatch']);

// Compras
Route::get('/compras', [PurchaseController::class, 'index']);
Route::post('/compras', [PurchaseController::class, 'store']);

// Ventas (Punto de Venta)
Route::get('/ventas', [SaleController::class, 'index']);
Route::post('/ventas', [SaleController::class, 'store']);
Route::get('/ventas/{id}', [SaleController::class, 'show']);
Route::post('/ventas/{id}/cancelar', [SaleController::class, 'cancel']);

// Adeudos y Cuentas por Cobrar
Route::get('/adeudos', [DebtController::class, 'index']);

// Abonos / Pagos
Route::get('/abonos', [PaymentController::class, 'index']);
Route::post('/abonos', [PaymentController::class, 'store']);

// Reportes Financieros y Ganancias
Route::get('/reportes/ganancias', [ReportController::class, 'profits']);
