<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            // Lot A vendor ack diagnostic: 0 ok, 1 busy, 2 bad param,
            // 3 hw fault, 4 unsupported. Diagnostic only — the state
            // machine still resolves on Acked/Failed (PRD part 2, §3).
            $table->integer('ack_code')->nullable()->after('acked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn('ack_code');
        });
    }
};
