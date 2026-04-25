<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('contractual_events', function (Blueprint $table) {
            $table->text('contractual_basis')->nullable()->after('description');
            $table->enum('contractual_basis_doc', [
                'contrato_base', 'bases_tecnicas', 'bases_admin', 'anexo', 'otro'
            ])->nullable()->after('contractual_basis');
            $table->date('notice_deadline')->nullable()->after('occurred_at');
            $table->date('notified_at')->nullable()->after('notice_deadline');
            $table->enum('notification_status', [
                'pendiente', 'notificado_a_tiempo', 'notificado_tarde', 'no_aplica'
            ])->default('pendiente')->after('notified_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('contractual_events', function (Blueprint $table) {
            $table->dropColumn([
                'contractual_basis', 'contractual_basis_doc',
                'notice_deadline', 'notified_at', 'notification_status',
            ]);
        });
    }
};
