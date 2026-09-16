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
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('uid_scanned');
            $table->foreignId('card_id')->nullable()->constrained('nfc_cards')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->timestamp('scanned_at');
            $table->enum('result', ['success', 'unregistered_card', 'inactive_card', 'inactive_student'])->default('success');
            $table->timestamps();

            $table->index('scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
