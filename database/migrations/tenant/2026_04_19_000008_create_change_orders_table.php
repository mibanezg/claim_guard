<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('change_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contractual_event_id')->nullable();
            $table->string('request_number')->unique();
            $table->enum('requested_by_party', ['mandante', 'contratista']);
            $table->text('description');
            $table->integer('schedule_impact_days')->default(0);
            $table->bigInteger('cost_impact')->default(0);
            $table->enum('status', [
                'solicitada', 'evaluacion', 'aprobada',
                'rechazada', 'aprobada_parcialmente',
            ])->default('solicitada');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->string('sharepoint_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('change_orders');
    }
};
