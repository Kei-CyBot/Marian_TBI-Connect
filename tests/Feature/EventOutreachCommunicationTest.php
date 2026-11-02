<?php

namespace Tests\Feature;

use App\Mail\OutreachMessageMail;
use App\Models\Contact;
use App\Models\OutreachMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventOutreachCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_send_outreach_email_with_attachments(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        Storage::fake('public');

        $sender = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $first = $this->createContact('First Recipient', 'first-recipient@example.test');
        $second = $this->createContact('Second Recipient', 'second-recipient@example.test');
        $attachment = UploadedFile::fake()->create('program.pdf', 120, 'application/pdf');

        $this->actingAs($sender)
            ->from(route('events'))
            ->post(route('event-outreach.send'), [
                'event_id' => 1,
                'message_type' => 'announcement',
                'subject' => 'Demo Day update',
                'body' => 'Join us for the upcoming Demo Day.',
                'recipients' => [$first->id, $second->id],
                'attachments' => [$attachment],
            ])
            ->assertRedirect(route('events'))
            ->assertSessionHas('email_status', 'Communication sent to 2 recipient(s).');

        Mail::assertSent(OutreachMessageMail::class, 2);
        Mail::assertSent(OutreachMessageMail::class, fn (OutreachMessageMail $mail): bool => $mail->hasTo($first->email)
            && $mail->messageType === 'announcement'
            && count($mail->attachmentMeta) === 1);
        Mail::assertSent(OutreachMessageMail::class, fn (OutreachMessageMail $mail): bool => $mail->hasTo($second->email));

        $message = OutreachMessage::query()->firstOrFail();
        $this->assertSame($sender->id, $message->sender_id);
        $this->assertSame(1, $message->event_id);
        $this->assertSame('announcement', $message->message_type);
        $this->assertNotNull($message->sent_at);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('program.pdf', $message->attachments[0]['name']);
        Storage::disk('public')->assertExists($message->attachments[0]['path']);
    }

    public function test_message_type_must_be_supported(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        Storage::fake('public');

        $contact = $this->createContact('Validation Recipient', 'validation-recipient@example.test');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->from(route('events'))
            ->post(route('event-outreach.send'), [
                'message_type' => 'spam',
                'subject' => 'Unsupported type',
                'body' => 'This should not be sent.',
                'recipients' => [$contact->id],
            ])
            ->assertSessionHasErrors('message_type');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('outreach_messages', 0);
    }

    public function test_attachment_must_not_exceed_the_size_limit(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        Storage::fake('public');

        $contact = $this->createContact('Attachment Recipient', 'attachment-recipient@example.test');
        $oversized = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->from(route('events'))
            ->post(route('event-outreach.send'), [
                'message_type' => 'email',
                'subject' => 'Oversized attachment',
                'body' => 'This should not be sent.',
                'recipients' => [$contact->id],
                'attachments' => [$oversized],
            ])
            ->assertSessionHasErrors('attachments.0');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('outreach_messages', 0);
    }

    public function test_standard_members_cannot_send_outreach_messages(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]))
            ->post(route('event-outreach.send'), [])
            ->assertForbidden();
    }

    private function createContact(string $name, string $email): Contact
    {
        return Contact::query()->create([
            'side' => 'external',
            'name' => $name,
            'email' => $email,
            'affiliation_type' => 'Company',
            'role' => 'Partner',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
    }
}
