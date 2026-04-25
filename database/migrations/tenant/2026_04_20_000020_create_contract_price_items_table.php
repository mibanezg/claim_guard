<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contract_price_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('code')->nullable()->comment('Código según el contrato');
            $table->string('description');
            $table->string('unit', 30)->comment('hr, m3, m2, gl, un, kg, etc.');
            $table->bigInteger('unit_cost')->comment('centavos');
            $table->enum('category', [
                'mano_obra', 'materiales', 'equipos',
                'subcontratos', 'gastos_generales', 'otro',
            ])->default('mano_obra');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['contract_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contract_price_items');
    }
};
