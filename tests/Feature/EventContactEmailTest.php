<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventContactEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_is_sent_individually_to_each_selected_contact(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $sender = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $first = $this->createContact('First Contact', 'first@example.test');
        $second = $this->createContact('Second Contact', 'second@example.test');

        $this->actingAs($sender)
            ->post(route('event-emails.send'), [
                'contact_ids' => [$first->id, $second->id],
                'subject' => 'Workshop invitation',
                'body' => 'Please join us at the workshop.',
            ])
            ->assertRedirect()
            ->assertSessionHas('email_status', 'Email sent individually to 2 contact(s).');

        Mail::assertSent(ContactMessage::class, 2);
        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail): bool => $mail->hasTo($first->email));
        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail): bool => $mail->hasTo($second->email));
    }

    public function test_member_cannot_send_contact_email(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($member)
            ->post(route('event-emails.send'), [])
            ->assertForbidden();
    }

    public function test_events_email_picker_can_filter_and_select_contacts_by_group(): void
    {
        $internal = $this->createContact('Internal Contact', 'internal@example.test', 'internal');
        $external = $this->createContact('External Contact', 'external@example.test');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('events'))
            ->assertOk()
            ->assertSee('Select all visible contacts')
            ->assertSee('Contact group')
            ->assertSee('Internal')
            ->assertSee('External')
            ->assertViewHas('broadcastContacts', fn ($contacts): bool => $contacts->contains(fn (Contact $contact): bool => $contact->is($internal) && $contact->side === 'internal')
                && $contacts->contains(fn (Contact $contact): bool => $contact->is($external) && $contact->side === 'external'));
    }

    private function createContact(string $name, string $email, string $side = 'external'): Contact
    {
        return Contact::query()->create([
            'side' => $side,
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
