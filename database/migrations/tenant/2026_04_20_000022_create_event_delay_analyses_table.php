<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('event_delay_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contractual_event_id')->unique();
            $table->unsignedBigInteger('affected_milestone_id')->nullable();
            $table->enum('delay_type', [
                'compensable', 'excusable', 'no_excusable', 'concurrente',
            ])->default('compensable');
            $table->boolean('is_critical_path')->default(true);
            $table->enum('analysis_method', [
                'as_planned_vs_as_built', 'time_impact',
                'collapsed_but_for', 'windows', 'contemporaneo',
            ])->default('as_planned_vs_as_built');
            $table->date('baseline_date')->nullable()->comment('Fecha planificada original del hito afectado');
            $table->date('impacted_date')->nullable()->comment('Fecha proyectada tras el evento');
            $table->integer('float_consumed')->nullable()->comment('Días de holgura consumidos');
            $table->string('concurrent_cause')->nullable()->comment('Si hay atraso concurrente, describir la otra causa');
            $table->text('narrative')->comment('Narrativa causal: qué actividades se vieron afectadas y por qué');
            $table->timestamps();

            $table->index('contractual_event_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('event_delay_analyses');
    }
};
