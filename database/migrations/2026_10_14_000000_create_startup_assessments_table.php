<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_assessments', function (Blueprint $table): void {
            $table->id();
            $table->string('startup_id', 64)->unique();
            $table->string('startup_name');
            $table->json('competency_ratings');
            $table->json('other_critical_skills');
            $table->unsignedSmallInteger('total_points');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_assessments');
    }
};
