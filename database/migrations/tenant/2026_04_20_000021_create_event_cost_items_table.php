<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('event_cost_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contractual_event_id');
            $table->unsignedBigInteger('contract_price_item_id')->nullable()->comment('null = precio manual');
            $table->string('description');
            $table->string('unit', 30);
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_cost')->comment('centavos');
            $table->bigInteger('amount')->comment('centavos = round(quantity * unit_cost)');
            $table->enum('cost_category', [
                'mano_obra_directa', 'materiales', 'equipos',
                'subcontratos', 'gastos_obra', 'overhead_sede', 'profit', 'otro',
            ])->default('mano_obra_directa');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('contractual_event_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('event_cost_items');
    }
};
