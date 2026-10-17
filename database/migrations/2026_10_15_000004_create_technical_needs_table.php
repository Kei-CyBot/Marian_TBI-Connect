<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_needs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('startup_id')->constrained()->cascadeOnDelete();
            $table->string('requirement');
            $table->text('description')->nullable();
            $table->decimal('subscription_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('months_needed')->nullable();
            $table->string('responsible_team_member')->nullable();
            $table->date('date_entry');
            $table->date('date_needed')->nullable();
            $table->string('resource_tool')->nullable();
            $table->string('prospect_resource_person')->nullable();
            $table->string('status', 40)->default('Ongoing');
            $table->string('priority', 24)->default('Moderate');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_needs');
    }
};
