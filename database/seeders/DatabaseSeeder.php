<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\LotService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $lotService = new LotService();

        // 1. Clientes
        $juan = Client::create([
            'nombre' => 'Juan Pérez López',
            'celular' => '5512345678',
            'notas' => 'Vecino de la esquina, paga cada viernes',
        ]);

        $maria = Client::create([
            'nombre' => 'María García Hernández',
            'celular' => '5587654321',
            'notas' => 'Cliente frecuente de mostrador',
        ]);

        $roberto = Client::create([
            'nombre' => 'Roberto Gómez Bolaños',
            'celular' => '5533445566',
            'notas' => 'Taller mecánico frente a la tienda',
        ]);

        $ana = Client::create([
            'nombre' => 'Ana Martínez Soto',
            'celular' => '5599887766',
            'notas' => 'Pedidos para eventos',
        ]);

        $carlos = Client::create([
            'nombre' => 'Carlos Rodríguez Cruz',
            'celular' => '5544332211',
            'notas' => 'Cliente nuevo',
        ]);

        // 2. Productos
        $coca = Product::create([
            'nombre' => 'Refresco Coca-Cola 600ml',
            'codigo' => '7501055300075',
            'descripcion' => 'Botella desechable pet',
            'activo' => true,
        ]);

        $sabritas = Product::create([
            'nombre' => 'Sabritas Sal 45g',
            'codigo' => '7501011115678',
            'descripcion' => 'Papas fritas con sal clásica',
            'activo' => true,
        ]);

        $emperador = Product::create([
            'nombre' => 'Galletas Emperador Chocolate 101g',
            'codigo' => '7501000673456',
            'descripcion' => 'Galletas tipo sandwich sabor chocolate',
            'activo' => true,
        ]);

        $leche = Product::create([
            'nombre' => 'Leche Entera Lala 1 Litro',
            'codigo' => '7501020512345',
            'descripcion' => 'Tetrapack 1L pasteurizada',
            'activo' => true,
        ]);

        $pan = Product::create([
            'nombre' => 'Pan Blanco Bimbo Grande 680g',
            'codigo' => '7501000112233',
            'descripcion' => 'Pan de caja tradicional',
            'activo' => true,
        ]);

        $cafe = Product::create([
            'nombre' => 'Café Nescafé Clásico 120g',
            'codigo' => '7501058612345',
            'descripcion' => 'Frasco soluble clásico',
            'activo' => true,
        ]);

        // 3. Compras y Lotes (Demostrando la regla de negocio de reutilización y 4 caracteres)
        // Compra 1: Coca-Cola a $12.00 (Genera lote 1 de 4 caracteres, ej. A7X2)
        $compra1 = $lotService->findOrCreateLot($coca->id, 12.00, 18.00, 24);
        $lotCoca1 = $compra1['lot'];
        Purchase::create([
            'product_id' => $coca->id,
            'lot_id' => $lotCoca1->id,
            'cantidad' => 24,
            'precio_compra' => 12.00,
            'precio_venta' => 18.00,
            'fecha_compra' => Carbon::now()->subDays(10),
            'notas' => 'Pedido inicial a repartidor Coca-Cola',
        ]);

        // Compra 2: Coca-Cola con el MISMO precio de compra ($12.00) -> Reutiliza el lote 1
        $compra2 = $lotService->findOrCreateLot($coca->id, 12.00, 18.00, 12);
        Purchase::create([
            'product_id' => $coca->id,
            'lot_id' => $lotCoca1->id,
            'cantidad' => 12,
            'precio_compra' => 12.00,
            'precio_venta' => 18.00,
            'fecha_compra' => Carbon::now()->subDays(6),
            'notas' => 'Resurtido semanal con mismo costo',
        ]);

        // Compra 3: Coca-Cola con NUEVO precio de compra ($13.50) -> Genera un NUEVO lote de 4 chars
        $compra3 = $lotService->findOrCreateLot($coca->id, 13.50, 19.50, 12);
        $lotCoca2 = $compra3['lot'];
        Purchase::create([
            'product_id' => $coca->id,
            'lot_id' => $lotCoca2->id,
            'cantidad' => 12,
            'precio_compra' => 13.50,
            'precio_venta' => 19.50,
            'fecha_compra' => Carbon::now()->subDays(2),
            'notas' => 'Aumento de precio del proveedor',
        ]);

        // Compras de otros productos
        $compraSabritas = $lotService->findOrCreateLot($sabritas->id, 11.00, 17.00, 20);
        $lotSabritas = $compraSabritas['lot'];
        Purchase::create([
            'product_id' => $sabritas->id,
            'lot_id' => $lotSabritas->id,
            'cantidad' => 20,
            'precio_compra' => 11.00,
            'precio_venta' => 17.00,
            'fecha_compra' => Carbon::now()->subDays(8),
            'notas' => 'Caja surtida Sabritas',
        ]);

        $compraEmperador = $lotService->findOrCreateLot($emperador->id, 14.00, 21.00, 15);
        $lotEmperador = $compraEmperador['lot'];
        Purchase::create([
            'product_id' => $emperador->id,
            'lot_id' => $lotEmperador->id,
            'cantidad' => 15,
            'precio_compra' => 14.00,
            'precio_venta' => 21.00,
            'fecha_compra' => Carbon::now()->subDays(7),
        ]);

        $compraLeche = $lotService->findOrCreateLot($leche->id, 22.00, 28.50, 18);
        $lotLeche = $compraLeche['lot'];
        Purchase::create([
            'product_id' => $leche->id,
            'lot_id' => $lotLeche->id,
            'cantidad' => 18,
            'precio_compra' => 22.00,
            'precio_venta' => 28.50,
            'fecha_compra' => Carbon::now()->subDays(5),
        ]);

        $compraPan = $lotService->findOrCreateLot($pan->id, 38.00, 48.00, 10);
        $lotPan = $compraPan['lot'];
        Purchase::create([
            'product_id' => $pan->id,
            'lot_id' => $lotPan->id,
            'cantidad' => 10,
            'precio_compra' => 38.00,
            'precio_venta' => 48.00,
            'fecha_compra' => Carbon::now()->subDays(4),
        ]);

        $compraCafe = $lotService->findOrCreateLot($cafe->id, 55.00, 72.00, 8);
        $lotCafe = $compraCafe['lot'];
        Purchase::create([
            'product_id' => $cafe->id,
            'lot_id' => $lotCafe->id,
            'cantidad' => 8,
            'precio_compra' => 55.00,
            'precio_venta' => 72.00,
            'fecha_compra' => Carbon::now()->subDays(3),
        ]);

        // 4. Ventas Realistas

        // Venta 1: Mostrador (Contado total)
        // 2 Coca-Cola Lote 1 ($36) + 1 Sabritas ($17) = $53.00
        $lotCoca1->decrement('stock_actual', 2);
        $lotSabritas->decrement('stock_actual', 1);
        $v1 = Sale::create([
            'client_id' => null,
            'total' => 53.00,
            'monto_pagado' => 53.00,
            'saldo_pendiente' => 0.00,
            'estado' => 'PAGADA',
            'fecha_venta' => Carbon::now()->subDays(3)->setHour(10)->setMinute(15),
            'notas' => 'Venta en efectivo mostrador',
        ]);
        SaleItem::create([
            'sale_id' => $v1->id,
            'product_id' => $coca->id,
            'lot_id' => $lotCoca1->id,
            'cantidad' => 2,
            'precio_compra_unitario' => 12.00,
            'precio_venta_unitario' => 18.00,
            'subtotal' => 36.00,
            'ganancia_item' => 12.00, // (18-12)*2
        ]);
        SaleItem::create([
            'sale_id' => $v1->id,
            'product_id' => $sabritas->id,
            'lot_id' => $lotSabritas->id,
            'cantidad' => 1,
            'precio_compra_unitario' => 11.00,
            'precio_venta_unitario' => 17.00,
            'subtotal' => 17.00,
            'ganancia_item' => 6.00, // (17-11)*1
        ]);
        Payment::create([
            'client_id' => null,
            'sale_id' => $v1->id,
            'monto' => 53.00,
            'metodo_pago' => 'efectivo',
            'fecha_pago' => $v1->fecha_venta,
            'notas' => 'Pago inicial en punto de venta',
        ]);

        // Venta 2: Juan Pérez (Venta con Adeudo)
        // 4 Leche Lala ($114) + 2 Pan Bimbo ($96) = Total $210.00. Pagó $100.00. Adeudo inicial $110.00
        $lotLeche->decrement('stock_actual', 4);
        $lotPan->decrement('stock_actual', 2);
        $v2 = Sale::create([
            'client_id' => $juan->id,
            'total' => 210.00,
            'monto_pagado' => 100.00,
            'saldo_pendiente' => 110.00,
            'estado' => 'CON_ADEUDO',
            'fecha_venta' => Carbon::now()->subDays(2)->setHour(14)->setMinute(30),
            'notas' => 'Abonó $100 en efectivo, resta $110',
        ]);
        SaleItem::create([
            'sale_id' => $v2->id,
            'product_id' => $leche->id,
            'lot_id' => $lotLeche->id,
            'cantidad' => 4,
            'precio_compra_unitario' => 22.00,
            'precio_venta_unitario' => 28.50,
            'subtotal' => 114.00,
            'ganancia_item' => 26.00, // (28.5-22)*4
        ]);
        SaleItem::create([
            'sale_id' => $v2->id,
            'product_id' => $pan->id,
            'lot_id' => $lotPan->id,
            'cantidad' => 2,
            'precio_compra_unitario' => 38.00,
            'precio_venta_unitario' => 48.00,
            'subtotal' => 96.00,
            'ganancia_item' => 20.00, // (48-38)*2
        ]);
        Payment::create([
            'client_id' => $juan->id,
            'sale_id' => $v2->id,
            'monto' => 100.00,
            'metodo_pago' => 'efectivo',
            'fecha_pago' => $v2->fecha_venta,
            'notas' => 'Anticipo inicial en venta',
        ]);

        // Venta 3: Roberto Gómez (Venta 100% a Crédito)
        // 3 Galletas Emperador ($63) + 2 Coca-Cola Lote 2 ($39) = Total $102.00. Pagó $0.00. Adeudo $102.00
        $lotEmperador->decrement('stock_actual', 3);
        $lotCoca2->decrement('stock_actual', 2);
        $v3 = Sale::create([
            'client_id' => $roberto->id,
            'total' => 102.00,
            'monto_pagado' => 0.00,
            'saldo_pendiente' => 102.00,
            'estado' => 'CON_ADEUDO',
            'fecha_venta' => Carbon::now()->subDays(1)->setHour(18)->setMinute(45),
            'notas' => 'Venta a crédito para el taller',
        ]);
        SaleItem::create([
            'sale_id' => $v3->id,
            'product_id' => $emperador->id,
            'lot_id' => $lotEmperador->id,
            'cantidad' => 3,
            'precio_compra_unitario' => 14.00,
            'precio_venta_unitario' => 21.00,
            'subtotal' => 63.00,
            'ganancia_item' => 21.00,
        ]);
        SaleItem::create([
            'sale_id' => $v3->id,
            'product_id' => $coca->id,
            'lot_id' => $lotCoca2->id,
            'cantidad' => 2,
            'precio_compra_unitario' => 13.50,
            'precio_venta_unitario' => 19.50,
            'subtotal' => 39.00,
            'ganancia_item' => 12.00,
        ]);

        // Venta 4: Ana Martínez (Pagada de contado)
        // 1 Café ($72) + 2 Sabritas ($34) = $106.00
        $lotCafe->decrement('stock_actual', 1);
        $lotSabritas->decrement('stock_actual', 2);
        $v4 = Sale::create([
            'client_id' => $ana->id,
            'total' => 106.00,
            'monto_pagado' => 106.00,
            'saldo_pendiente' => 0.00,
            'estado' => 'PAGADA',
            'fecha_venta' => Carbon::now()->setHour(9)->setMinute(30),
            'notas' => 'Pagado con transferencia',
        ]);
        SaleItem::create([
            'sale_id' => $v4->id,
            'product_id' => $cafe->id,
            'lot_id' => $lotCafe->id,
            'cantidad' => 1,
            'precio_compra_unitario' => 55.00,
            'precio_venta_unitario' => 72.00,
            'subtotal' => 72.00,
            'ganancia_item' => 17.00,
        ]);
        SaleItem::create([
            'sale_id' => $v4->id,
            'product_id' => $sabritas->id,
            'lot_id' => $lotSabritas->id,
            'cantidad' => 2,
            'precio_compra_unitario' => 11.00,
            'precio_venta_unitario' => 17.00,
            'subtotal' => 34.00,
            'ganancia_item' => 12.00,
        ]);
        Payment::create([
            'client_id' => $ana->id,
            'sale_id' => $v4->id,
            'monto' => 106.00,
            'metodo_pago' => 'transferencia',
            'fecha_pago' => $v4->fecha_venta,
            'notas' => 'Transferencia bancaria directa',
        ]);

        // 5. Abonos posteriores
        // Juan Pérez abona $50 a su venta #2 (Saldo pendiente baja de $110 a $60)
        $v2->saldo_pendiente = 60.00;
        $v2->monto_pagado = 150.00;
        $v2->save();
        Payment::create([
            'client_id' => $juan->id,
            'sale_id' => $v2->id,
            'monto' => 50.00,
            'metodo_pago' => 'efectivo',
            'fecha_pago' => Carbon::now()->subHours(12),
            'notas' => 'Abono en efectivo por la tarde',
        ]);

        // Roberto Gómez abona $40 a su venta #3 (Saldo pendiente baja de $102 a $62)
        $v3->saldo_pendiente = 62.00;
        $v3->monto_pagado = 40.00;
        $v3->save();
        Payment::create([
            'client_id' => $roberto->id,
            'sale_id' => $v3->id,
            'monto' => 40.00,
            'metodo_pago' => 'efectivo',
            'fecha_pago' => Carbon::now()->subHours(4),
            'notas' => 'Abono de fin de semana',
        ]);
    }
}
