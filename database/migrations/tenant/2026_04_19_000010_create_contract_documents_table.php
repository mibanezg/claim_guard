<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractual_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('letter_id')->nullable()->constrained('contract_letters')->nullOnDelete();
            $table->foreignId('change_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('category', [
                'carta_emitida', 'carta_recibida', 'evento',
                'orden_cambio', 'programa', 'expediente', 'otro',
            ])->default('otro');
            $table->string('sharepoint_id')->nullable();
            $table->string('sharepoint_url', 2048)->nullable();
            $table->string('local_path')->nullable();
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contract_documents');
    }
};
