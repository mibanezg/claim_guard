<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('contracts', function (Blueprint $table) {
            $table->longText('contract_text')->nullable()->after('clauses');
            $table->string('contract_pdf_name')->nullable()->after('contract_text');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('contracts', function (Blueprint $table) {
            $table->dropColumn(['contract_text', 'contract_pdf_name']);
        });
    }
};
