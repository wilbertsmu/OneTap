<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance_logs MODIFY result ENUM('success', 'unregistered_card', 'inactive_card', 'inactive_student', 'inactive_employee') DEFAULT 'success'");
    }

    public function down(): void
    {
        DB::statement("UPDATE attendance_logs SET result = 'inactive_student' WHERE result = 'inactive_employee'");
        DB::statement("ALTER TABLE attendance_logs MODIFY result ENUM('success', 'unregistered_card', 'inactive_card', 'inactive_student') DEFAULT 'success'");
    }
};
