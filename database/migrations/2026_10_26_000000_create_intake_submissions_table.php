<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->string('name');
            $table->string('email');
            $table->string('track', 16);
            $table->json('data');
            $table->string('status', 24)->default('Pending');
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_submissions');
    }
};
