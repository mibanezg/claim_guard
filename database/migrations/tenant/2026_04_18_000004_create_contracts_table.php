<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('number')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['obra', 'suministro', 'servicios', 'EPC', 'mixto']);
            $table->foreignId('mandante_company_id')->constrained('companies');
            $table->foreignId('contractor_company_id')->constrained('companies');
            $table->bigInteger('original_amount');
            $table->bigInteger('current_amount');
            $table->enum('currency', ['CLP', 'USD'])->default('CLP');
            $table->date('contractual_start_date');
            $table->date('contractual_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('projected_end_date')->nullable();
            $table->integer('notification_days')->default(5);
            $table->enum('status', ['borrador', 'vigente', 'suspendido', 'terminado', 'en_disputa'])->default('borrador');
            $table->json('clauses')->nullable();
            $table->string('applicable_law')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->timestamp('ms_project_imported_at')->nullable();
            $table->timestamp('primavera_imported_at')->nullable();
            $table->unsignedBigInteger('created_by'); // FK a landlord.users — sin constraint cross-DB
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contracts');
    }
};
