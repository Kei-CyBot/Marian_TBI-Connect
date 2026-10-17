<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_drive_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('singleton_key')->unique()->default('marian-tbi');
            $table->string('google_email');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at')->nullable();
            $table->string('root_folder_id')->nullable();
            $table->text('root_folder_url')->nullable();
            $table->foreignId('connected_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_drive_connections');
    }
};
