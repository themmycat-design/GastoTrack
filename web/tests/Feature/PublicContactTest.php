<?php

namespace Tests\Feature;

use App\Mail\PublicContactAcknowledgement;
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
            ->assertSee('data-gt-contact-form', false)
            ->assertSee('data-gt-contact-status', false)
            ->assertSee('name="message"', false)
            ->assertSee('Send message');
    }

    public function test_landing_contact_message_returns_to_the_landing_section(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('services.gastotrack.contact_email', 'team@example.test');

        $this->assertGuest();

        $this->post(route('contact.send'), array_merge($this->validMessage(), ['source' => 'landing']))
            ->assertRedirect(url('/').'#contact')
            ->assertSessionHas('contact_sent');

        Mail::assertSent(PublicContactMessage::class, 1);
        Mail::assertSent(PublicContactAcknowledgement::class, 1);
    }

    public function test_landing_contact_message_can_be_sent_without_a_page_redirect(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('services.gastotrack.contact_email', 'team@example.test');

        $this->postJson(route('contact.send'), array_merge($this->validMessage(), ['source' => 'landing']))
            ->assertOk()
            ->assertJsonPath('message', 'Your message was sent to the GastoTrack team. We sent a thank-you email to your address.')
            ->assertJsonPath('acknowledgement_sent', true);

        Mail::assertSent(PublicContactMessage::class, function (PublicContactMessage $mail) {
            return $mail->hasTo('team@example.test')
                && $mail->envelope()->replyTo[0]->address === 'visitor@example.test';
        });
        Mail::assertSent(PublicContactAcknowledgement::class, function (PublicContactAcknowledgement $mail) {
            return $mail->hasTo('visitor@example.test')
                && $mail->envelope()->replyTo[0]->address === 'team@example.test';
        });
    }

    public function test_landing_contact_json_validation_failure_does_not_send_mail(): void
    {
        Mail::fake();

        $this->postJson(route('contact.send'), array_merge($this->validMessage(), ['message' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        Mail::assertNothingSent();
    }

    public function test_landing_contact_json_reports_unavailable_delivery(): void
    {
        Mail::fake();
        config()->set('mail.default', 'log');

        $this->postJson(route('contact.send'), array_merge($this->validMessage(), ['source' => 'landing']))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Message delivery is unavailable right now. Please try again later.');

        Mail::assertNothingSent();
    }

    public function test_missing_admin_inbox_sends_neither_email(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');
        config()->set('services.gastotrack.contact_email', 'invalid-address');

        $this->postJson(route('contact.send'), $this->validMessage())
            ->assertStatus(503);

        Mail::assertNothingSent();
    }

    public function test_acknowledgement_failure_does_not_hide_the_delivered_admin_message(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('services.gastotrack.contact_email', 'team@example.test');

        Mail::shouldReceive('to')->once()->with('team@example.test')->ordered()->andReturn(new class
        {
            public function send(PublicContactMessage $mail): void {}
        });
        Mail::shouldReceive('to')->once()->with('visitor@example.test')->ordered()
            ->andThrow(new \RuntimeException('Simulated acknowledgement failure'));

        $this->postJson(route('contact.send'), $this->validMessage())
            ->assertOk()
            ->assertJsonPath('acknowledgement_sent', false)
            ->assertJsonPath('message', 'Your message reached the GastoTrack team, but we could not send the thank-you email.');
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
                && $mail->envelope()->replyTo[0]->address === 'visitor@example.test';
        });
        Mail::assertSent(PublicContactAcknowledgement::class, function (PublicContactAcknowledgement $mail) {
            return $mail->hasTo('visitor@example.test')
                && $mail->senderName === 'Sample Visitor';
        });
    }

    public function test_contact_mailable_can_be_sent_through_an_in_memory_transport(): void
    {
        config()->set('mail.from.address', 'gastotrack@example.test');
        config()->set('mail.from.name', 'GastoTrack');

        $mailer = Mail::mailer('array');
        $mailer->to('team@example.test')->send(new PublicContactMessage(
            'Sample Visitor',
            'visitor@example.test',
            'Account question',
            'How can I access my shop account?',
        ));

        $sent = $mailer->getSymfonyTransport()->messages()->first();
        $this->assertNotNull($sent);
        $this->assertSame('visitor@example.test', $sent->getOriginalMessage()->getReplyTo()[0]->getAddress());

        $mailer->to('visitor@example.test')->send(new PublicContactAcknowledgement(
            'Sample Visitor',
            'team@example.test',
        ));

        $acknowledgement = $mailer->getSymfonyTransport()->messages()->last();
        $this->assertNotNull($acknowledgement);
        $this->assertSame('visitor@example.test', $acknowledgement->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertSame('gastotrack@example.test', $acknowledgement->getOriginalMessage()->getFrom()[0]->getAddress());
        $this->assertSame('team@example.test', $acknowledgement->getOriginalMessage()->getReplyTo()[0]->getAddress());
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
        Mail::assertSent(PublicContactAcknowledgement::class, 3);
    }
}
