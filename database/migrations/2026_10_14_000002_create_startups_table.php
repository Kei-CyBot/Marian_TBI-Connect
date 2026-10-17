<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('founder');
            $table->string('contact_email');
            $table->string('track', 16);
            $table->string('venture_type')->nullable();
            $table->string('status', 40);
            $table->unsignedInteger('cohort_id')->nullable();
            $table->string('campus_department')->nullable();
            $table->string('legal_entity_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startups');
    }
};
