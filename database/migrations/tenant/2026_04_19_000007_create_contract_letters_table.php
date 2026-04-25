<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contract_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contractual_event_id')->nullable();
            $table->string('letter_number')->unique();
            $table->enum('type', [
                'notificacion', 'reserva_derechos', 'respuesta',
                'cobranza', 'acta_reunion', 'memorando',
            ]);
            $table->string('subject');
            $table->unsignedBigInteger('from_company_id');
            $table->unsignedBigInteger('to_company_id');
            $table->date('issued_at')->nullable();
            $table->date('received_at')->nullable();
            $table->date('response_deadline')->nullable();
            $table->integer('response_days')->nullable();
            $table->enum('status', ['borrador', 'emitida', 'recibida', 'respondida', 'vencida'])->default('borrador');
            $table->json('clauses_referenced')->nullable();
            $table->string('sharepoint_id')->nullable();
            $table->string('sharepoint_url', 1000)->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->text('content_draft')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contract_letters');
    }
};
