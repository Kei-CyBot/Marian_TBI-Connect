<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_registration_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email');
            $table->string('stream', 16);
            $table->json('application_data');
            $table->string('status', 24)->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_registration_applications');
    }
};
