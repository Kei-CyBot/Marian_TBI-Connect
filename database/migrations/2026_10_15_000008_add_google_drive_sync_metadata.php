<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            $table->string('google_drive_folder_id')->nullable();
            $table->text('google_drive_folder_url')->nullable();
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->string('google_drive_response_file_id')->nullable();
            $table->text('google_drive_response_url')->nullable();
            $table->string('google_drive_attachment_file_id')->nullable();
            $table->text('google_drive_attachment_url')->nullable();
            $table->timestamp('drive_synced_at')->nullable();
        });

        Schema::create('startup_folder_drive_permissions', function (Blueprint $table): void {
            $table->foreignId('startup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_permission_id');
            $table->timestamps();
            $table->primary(['startup_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_folder_drive_permissions');

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropColumn([
                'google_drive_response_file_id',
                'google_drive_response_url',
                'google_drive_attachment_file_id',
                'google_drive_attachment_url',
                'drive_synced_at',
            ]);
        });

        Schema::table('startups', function (Blueprint $table): void {
            $table->dropColumn(['google_drive_folder_id', 'google_drive_folder_url']);
        });
    }
};
