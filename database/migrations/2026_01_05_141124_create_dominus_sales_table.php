<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('dominus_sales', function (Blueprint $table) {
        $table->bigIncrements('id_control');

        $table->unsignedBigInteger('run_id')->nullable();

        $table->unsignedInteger('id_eds');
        $table->string('no_pedido', 50);
        $table->string('no_factura', 50)->nullable();
        $table->dateTime('fecha_factura')->nullable();

        $table->string('doc_vend', 30)->nullable();
        $table->string('nit_cliente', 30)->nullable();
        $table->string('nombre_cliente', 180)->nullable();

        $table->string('convenio', 80)->nullable();
        $table->string('panel', 30)->nullable();
        $table->string('cara', 30)->nullable();

        $table->string('placa', 15)->nullable();
        $table->integer('km')->nullable();
        $table->string('otro', 120)->nullable();

        $table->string('producto', 120);
        $table->string('referencia', 60)->nullable();

        $table->decimal('cantidad', 12, 3)->default(0);
        $table->decimal('ppu', 14, 3)->default(0);
        $table->decimal('iva', 14, 3)->default(0);
        $table->decimal('ipoconsumo', 14, 3)->default(0);
        $table->decimal('total', 16, 3)->default(0);

        $table->timestamps();

        // Dedupe clave (igual a tu PHP): (no_pedido, producto, fecha_factura)
        $table->unique(['no_pedido', 'producto', 'fecha_factura'], 'uq_sale_key');

        $table->index(['id_eds', 'fecha_factura']);
        $table->index(['nit_cliente']);
        $table->index(['placa']);

        $table->foreign('run_id')->references('id')->on('dominus_runs')->nullOnDelete();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dominus_sales');
    }
};