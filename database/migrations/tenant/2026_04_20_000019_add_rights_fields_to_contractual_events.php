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
            $table->boolean('rights_reserved')->default(false)->after('notification_status');
            $table->date('rights_reserved_at')->nullable()->after('rights_reserved');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('contractual_events', function (Blueprint $table) {
            $table->dropColumn(['rights_reserved', 'rights_reserved_at']);
        });
    }
};
