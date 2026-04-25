<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('report_number');
            $table->enum('weather', ['bueno', 'nublado', 'lluvia', 'viento_fuerte', 'nevada', 'otro'])->default('bueno');
            $table->integer('temperature')->nullable()->comment('°C');
            $table->text('work_executed');
            $table->json('personnel_on_site')->nullable()->comment('[{trade, count}]');
            $table->json('equipment_on_site')->nullable()->comment('[{name, quantity}]');
            $table->text('materials_received')->nullable();
            $table->text('instructions_received')->nullable()->comment('Instrucciones verbales del mandante — clave para claims');
            $table->text('issues_encountered')->nullable();
            $table->text('safety_incidents')->nullable();
            $table->text('visitors')->nullable();
            $table->text('general_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['contract_id', 'report_date']);
        });

        Schema::connection('tenant')->create('daily_report_event', function (Blueprint $table) {
            $table->foreignId('daily_report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('contractual_event_id')->constrained('contractual_events')->cascadeOnDelete();
            $table->primary(['daily_report_id', 'contractual_event_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('daily_report_event');
        Schema::connection('tenant')->dropIfExists('daily_reports');
    }
};
