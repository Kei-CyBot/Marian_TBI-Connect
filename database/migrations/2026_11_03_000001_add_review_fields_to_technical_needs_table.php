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
            $table->foreignId('user_id')->nullable()->after('startup_id')->constrained()->nullOnDelete();
            $table->string('attachment_path')->nullable()->after('remarks');
            $table->string('approval_attachment_path')->nullable()->after('attachment_path');
            $table->text('review_notes')->nullable()->after('approval_attachment_path');
            $table->foreignId('reviewed_by')->nullable()->after('review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('status', 40)->default('pending')->change();
        });

        DB::table('technical_needs')
            ->whereIn('status', ['Ongoing', 'Done'])
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('technical_needs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['attachment_path', 'approval_attachment_path', 'review_notes']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
            $table->string('status', 40)->default('Ongoing')->change();
        });
    }
};
