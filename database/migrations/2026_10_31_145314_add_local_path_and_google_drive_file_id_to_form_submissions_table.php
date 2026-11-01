<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->string('local_path')->nullable()->after('prerequisite_size_bytes');
            if (! Schema::hasColumn('form_submissions', 'google_drive_file_id')) {
                $table->string('google_drive_file_id')->nullable()->after('local_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropColumn(['local_path']);
            if (Schema::hasColumn('form_submissions', 'google_drive_file_id')) {
                $table->dropColumn(['google_drive_file_id']);
            }
        });
    }
};
