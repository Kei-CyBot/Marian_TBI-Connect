<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_startup_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('startup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('confirmed_by')->constrained('users')->cascadeOnDelete();
            $table->string('verification_method', 40);
            $table->text('verification_notes');
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['contact_id', 'startup_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_startup_assignments');
    }
};
