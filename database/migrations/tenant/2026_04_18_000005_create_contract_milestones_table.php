<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contract_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('planned_date');
            $table->date('actual_date')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->boolean('generates_notification')->default(false);
            $table->enum('status', ['pendiente', 'en_progreso', 'completado', 'atrasado'])->default('pendiente');
            $table->enum('source', ['manual', 'ms_project', 'primavera'])->default('manual');
            $table->string('external_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contract_milestones');
    }
};
