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
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfid_card_id')->nullable()->constrained('rfid_cards')->nullOnDelete();
            $table->string('scanned_uid');
            $table->string('mode');
            $table->string('status')->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
