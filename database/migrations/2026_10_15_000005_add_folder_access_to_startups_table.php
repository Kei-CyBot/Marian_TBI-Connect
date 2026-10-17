<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            $table->boolean('folder_access_restricted')->default(false);
            $table->foreignId('folder_access_manager_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('folder_access_manager_id');
            $table->dropColumn('folder_access_restricted');
        });
    }
};
