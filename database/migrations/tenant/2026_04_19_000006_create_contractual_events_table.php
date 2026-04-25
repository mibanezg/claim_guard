<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contractual_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->enum('type', [
                'orden_cambio', 'trabajo_adicional', 'condicion_imprevista',
                'atraso_mandante', 'atraso_contratista', 'suspension',
                'entrega_frente', 'no_conformidad', 'disputa', 'otro',
            ]);
            $table->date('occurred_at');
            $table->text('description');
            $table->enum('responsible_party', ['mandante', 'contratista', 'fuerza_mayor', 'tercero']);
            $table->integer('schedule_impact_days')->default(0);
            $table->bigInteger('cost_impact')->default(0);
            $table->enum('resolution_status', ['pendiente', 'negociacion', 'resuelto', 'escalado'])->default('pendiente');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contractual_events');
    }
};
