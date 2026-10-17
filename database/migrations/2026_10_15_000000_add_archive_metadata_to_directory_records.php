<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            $table->foreignId('archived_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason')->nullable()->after('archived_by');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreignId('archived_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason')->nullable()->after('archived_by');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archive_reason');
        });

        Schema::table('startups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archive_reason');
        });
    }
};
