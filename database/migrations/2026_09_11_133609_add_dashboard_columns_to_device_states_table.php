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
        Schema::table('device_states', function (Blueprint $table) {
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->decimal('last_power_w', 10, 2)->nullable();
            $table->string('last_switch_state', 16)->nullable(); // SwitchState enum
            $table->foreignId('pending_command_id')->nullable()->constrained('commands')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_states', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_command_id');
            $table->dropColumn(['is_online', 'last_seen_at', 'last_power_w', 'last_switch_state']);
        });
    }
};
