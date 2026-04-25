<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contract_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('exposure_assessment')->nullable();
            $table->json('strong_points')->nullable();
            $table->json('weak_points')->nullable();
            $table->json('urgent_actions')->nullable();
            $table->json('pattern_observations')->nullable();
            $table->json('key_clauses')->nullable();
            $table->integer('estimated_exposure_days')->nullable();
            $table->bigInteger('estimated_exposure_cost')->nullable()->comment('centavos');
            $table->enum('analysis_confidence', ['alta', 'media', 'baja'])->nullable();
            $table->text('confidence_note')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contract_ai_analyses');
    }
};
