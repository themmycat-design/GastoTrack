<?php

namespace Tests\Feature;

use App\Mail\PublicContactMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicContactTest extends TestCase
{
    public function test_landing_page_contains_the_contact_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="contact"', false)
            ->assertSee('name="message"', false)
            ->assertSee('Send message');
    }

    public function test_landing_contact_message_returns_to_the_landing_section(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('services.gastotrack.contact_email', 'team@example.test');

        $this->post(route('contact.send'), array_merge($this->validMessage(), ['source' => 'landing']))
            ->assertRedirect(url('/').'#contact')
            ->assertSessionHas('contact_sent');

        Mail::assertSent(PublicContactMessage::class, 1);
    }

    private function validMessage(): array
    {
        return [
            'name' => 'Sample Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'Account question',
            'message' => 'How can I access my shop account?',
            'website' => '',
        ];
    }

    public function test_valid_contact_message_is_sent_to_the_project_email_with_reply_to(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('services.gastotrack.contact_email', 'team@example.test');

        $this->post(route('contact.send'), $this->validMessage())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('contact_sent');

        Mail::assertSent(PublicContactMessage::class, function (PublicContactMessage $mail) {
            return $mail->hasTo('team@example.test')
                && $mail->senderEmail === 'visitor@example.test'
                && $mail->envelope()->replyTo[0]->getAddress() === 'visitor@example.test';
        });
    }

    public function test_invalid_or_bot_contact_message_is_not_sent(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');

        $this->from(route('contact'))->post(route('contact.send'), array_merge($this->validMessage(), [
            'email' => 'not-an-email',
            'message' => 'short',
            'website' => 'spam.example',
        ]))->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['email', 'message', 'website']);

        Mail::assertNothingSent();
    }

    public function test_log_mailer_does_not_claim_the_message_was_delivered(): void
    {
        Mail::fake();
        config()->set('mail.default', 'log');

        $this->from(route('contact'))->post(route('contact.send'), $this->validMessage())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('contact_error')
            ->assertSessionMissing('contact_sent');

        Mail::assertNothingSent();
    }

    public function test_contact_form_limits_repeated_submissions(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('contact.send'), $this->validMessage())->assertRedirect(route('contact'));
        }

        $this->post(route('contact.send'), $this->validMessage())->assertStatus(429);
        Mail::assertSent(PublicContactMessage::class, 3);
    }
}
