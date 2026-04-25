<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // SQLite no valida ENUM — el MODIFY solo aplica en MySQL/MariaDB
        if (DB::connection('tenant')->getDriverName() !== 'sqlite') {
            DB::connection('tenant')->statement("
                ALTER TABLE contract_documents
                MODIFY COLUMN category ENUM(
                    'carta_emitida','carta_recibida','evento','orden_cambio','programa','expediente','otro',
                    'contrato_base','bases_tecnicas','bases_admin','anexo','addenda','especificaciones'
                ) NOT NULL DEFAULT 'otro'
            ");
        }

        Schema::connection('tenant')->table('contract_documents', function (Blueprint $table) {
            $table->boolean('is_constitutive')->default(false)->after('category');
            $table->longText('extracted_text')->nullable()->after('is_constitutive');
            $table->unsignedSmallInteger('precedence_order')->default(0)->after('extracted_text');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('contract_documents', function (Blueprint $table) {
            $table->dropColumn(['is_constitutive', 'extracted_text', 'precedence_order']);
        });

        if (DB::connection('tenant')->getDriverName() !== 'sqlite') {
            DB::connection('tenant')->statement("
                ALTER TABLE contract_documents
                MODIFY COLUMN category ENUM(
                    'carta_emitida','carta_recibida','evento','orden_cambio','programa','expediente','otro'
                ) NOT NULL DEFAULT 'otro'
            ");
        }
    }
};
