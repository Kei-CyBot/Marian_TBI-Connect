<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('form_id');
            $table->string('form_name');
            $table->string('startup_name');
            $table->string('track', 20);
            $table->json('data');
            $table->string('approval_status', 30)->default('submitted');
            $table->string('prerequisite_path')->nullable();
            $table->string('prerequisite_original_name')->nullable();
            $table->string('prerequisite_mime_type', 100)->nullable();
            $table->unsignedBigInteger('prerequisite_size_bytes')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'approval_status']);
            $table->index(['startup_name', 'track']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
