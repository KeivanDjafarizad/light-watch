<?php

use App\Models\SwitchState;
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
        Schema::create('device_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained();
            $table->timestamp('received_at');
            $table->timestamp('device_reported_at')->nullable();
            $table->decimal('power_w', 10, 2)->nullable();
            $table->decimal('energy_wh_cumulative', 14, 2)->nullable();
            $table->decimal('energy_wh_delta', 12, 2)->nullable();
            $table->boolean('counter_reset_detected')->default(false);
            $table->enum('switch_state', SwitchState::cases())->default(SwitchState::Unknown->value);
            $table->json('alarm_codes')->nullable();
            $table->json('raw_payload');
            $table->string('dedup_key', 64)->unique();
            $table->timestamps();

            $table->index(['device_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_readings');
    }
};
