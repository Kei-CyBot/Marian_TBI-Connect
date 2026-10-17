<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('side', 16);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('affiliation_type');
            $table->string('role');
            $table->string('organization')->nullable();
            $table->string('academic_program')->nullable();
            $table->string('academic_year')->nullable();
            $table->json('domains');
            $table->string('domain_other')->nullable();
            $table->json('technical_skills');
            $table->string('technical_other')->nullable();
            $table->string('skill_details', 200)->nullable();
            $table->string('nda_status', 32)->default('Pending');
            $table->string('verification_status', 80)->default('Verified Contact');
            $table->json('incubatees');
            $table->json('activity_log');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
