<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('service')->unique(); // microsoft_graph
            $table->string('client_id')->nullable();
            $table->string('tenant_azure_id')->nullable();
            $table->text('client_secret_encrypted')->nullable();
            $table->string('site_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tenant_integrations');
    }
};
