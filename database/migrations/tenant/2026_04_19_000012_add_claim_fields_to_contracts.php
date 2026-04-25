<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('contracts', function (Blueprint $table) {
            $table->longText('claim_summary')->nullable()->after('jurisdiction');
            $table->string('claim_pdf_path')->nullable()->after('claim_summary');
            $table->string('claim_pdf_sharepoint_id')->nullable()->after('claim_pdf_path');
            $table->string('claim_pdf_sharepoint_url')->nullable()->after('claim_pdf_sharepoint_id');
            $table->timestamp('claim_generated_at')->nullable()->after('claim_pdf_sharepoint_url');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('contracts', function (Blueprint $table) {
            $table->dropColumn(['claim_summary', 'claim_pdf_path', 'claim_pdf_sharepoint_id', 'claim_pdf_sharepoint_url', 'claim_generated_at']);
        });
    }
};
