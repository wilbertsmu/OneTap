<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance_logs MODIFY uid_scanned VARCHAR(255) NULL');

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->enum('source', ['scan', 'manual'])->default('scan')->after('result');
            $table->foreignId('encoded_by_id')->nullable()->after('source')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('encoded_by_id');
            $table->dropColumn('source');
        });

        DB::statement('ALTER TABLE attendance_logs MODIFY uid_scanned VARCHAR(255) NOT NULL');
    }
};
