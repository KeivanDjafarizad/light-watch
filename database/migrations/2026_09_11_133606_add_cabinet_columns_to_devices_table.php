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
        Schema::table('devices', function (Blueprint $table) {
            // Optional display/filtering metadata populated by `plant:import`
            // (assets/plant.csv). Nullable on purpose: the dashboard must work
            // correctly when the import has never run (see PRD §4/§12).
            $table->string('cabinet_code', 32)->nullable()->after('label');
            $table->string('cabinet_name')->nullable()->after('cabinet_code');

            $table->index('cabinet_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['cabinet_code']);
            $table->dropColumn(['cabinet_code', 'cabinet_name']);
        });
    }
};
