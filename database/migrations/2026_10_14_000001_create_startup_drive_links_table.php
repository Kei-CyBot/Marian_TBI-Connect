<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_drive_links', function (Blueprint $table): void {
            $table->id();
            $table->string('startup_id', 64)->index();
            $table->string('startup_name');
            $table->string('label', 120);
            $table->text('url');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_drive_links');
    }
};
