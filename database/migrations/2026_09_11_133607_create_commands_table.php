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
        Schema::create('commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained();
            // CommandType enum: on|off|dim (string column + backed enum cast,
            // same convention as devices.vendor / device_readings.switch_state).
            $table->string('type', 16);
            $table->json('payload')->nullable(); // e.g. {"level": 60}
            // CommandStatus enum: pending|sent|acked|failed|confirmed_by_telemetry|unconfirmed
            $table->string('status', 24)->default('pending');
            $table->timestamp('issued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reconcile_by')->nullable(); // deadline for Lot C reconciliation
            $table->timestamps();

            $table->index(['status', 'reconcile_by']);
            $table->index(['device_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
