<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('claim_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->enum('score_level', ['bajo', 'medio', 'alto', 'critico']);
            $table->unsignedTinyInteger('score_value')->default(0);
            $table->json('factors');
            $table->json('recommendations')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['contract_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('claim_risk_scores');
    }
};
