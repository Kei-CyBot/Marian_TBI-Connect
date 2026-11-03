<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('status')->default('scheduled')->after('host');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('actual_attendees_count')->nullable();
            $table->text('post_event_notes')->nullable();
            $table->string('post_event_document_path')->nullable();
            $table->string('post_event_document_original_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'completed_at',
                'cancelled_at',
                'cancellation_reason',
                'actual_attendees_count',
                'post_event_notes',
                'post_event_document_path',
                'post_event_document_original_name',
            ]);
        });
    }
};
