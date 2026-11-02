<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_messages', function (Blueprint $table): void {
            $table->id();
            // shortcut: events are static JS data, add a real FK when events become persisted.
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('message_type')->default('email');
            $table->string('subject');
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
    }
};
