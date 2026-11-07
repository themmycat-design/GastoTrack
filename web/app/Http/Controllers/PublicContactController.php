<?php

namespace App\Http\Controllers;

use App\Mail\PublicContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PublicContactController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:254'],
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'max:0'], // Hidden field catches simple form bots.
        ]);

        $recipient = config('services.gastotrack.contact_email');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
            return back()->withInput()->with('contact_error', 'Message delivery is not configured yet. Please use the email address below.');
        }

        try {
            Mail::to($recipient)->send(new PublicContactMessage(
                $data['name'],
                $data['email'],
                $data['subject'],
                $data['message'],
            ));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Contact form mail delivery failed', ['exception' => $exception::class]);

            return back()->withInput()->with('contact_error', 'We could not send your message. Please try again or use the email address below.');
        }

        $destination = $request->input('source') === 'landing' ? url('/').'#contact' : route('contact');

        return redirect()->to($destination)->with('contact_sent', 'Your message was sent. Thank you for contacting GastoTrack.');
    }
}
