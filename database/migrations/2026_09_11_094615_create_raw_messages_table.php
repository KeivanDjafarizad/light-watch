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
        Schema::create('raw_messages', function (Blueprint $table) {
            $table->id();
            $table->string('lot', 4);
            $table->string('topic');
            $table->text('payload');
            $table->timestamp('received_at');
            $table->string('dedup_key', 64)->unique();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['lot', 'received_at']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_messages');
    }
};
