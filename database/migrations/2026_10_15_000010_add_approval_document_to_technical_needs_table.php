<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->string('approval_document_path')->nullable();
            $table->string('approval_document_original_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->dropColumn(['approval_document_path', 'approval_document_original_name']);
        });
    }
};
