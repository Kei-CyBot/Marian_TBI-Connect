<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->string('approval_attachment_original_name')->nullable()->after('approval_attachment_path');
        });

        DB::table('technical_needs')
            ->whereNull('approval_attachment_path')
            ->whereNotNull('approval_document_path')
            ->update([
                'approval_attachment_path' => DB::raw('approval_document_path'),
                'approval_attachment_original_name' => DB::raw('approval_document_original_name'),
            ]);

        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->dropColumn(['approval_document_path', 'approval_document_original_name']);
        });
    }

    public function down(): void
    {
        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->string('approval_document_path')->nullable();
            $table->string('approval_document_original_name')->nullable();
        });

        DB::table('technical_needs')
            ->whereNotNull('approval_attachment_path')
            ->update(['approval_document_path' => DB::raw('approval_attachment_path')]);

        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->dropColumn('approval_attachment_original_name');
        });
    }
};
