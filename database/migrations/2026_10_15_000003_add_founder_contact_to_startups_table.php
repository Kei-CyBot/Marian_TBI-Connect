<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            $table->foreignId('founder_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('startups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('founder_contact_id');
        });
    }
};
