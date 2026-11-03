<?php

namespace App\Http\Controllers;

use App\Mail\PlainTextNotification;
use App\Models\CompanyBranch;
use App\Models\Contact;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ContactController extends Controller
{
    public function index(): View
    {
        $settings = SiteSetting::query()->pluck('setting_value', 'setting_key');
        $branches = CompanyBranch::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('contact.index', compact('settings', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $contact = Contact::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower($data['email']),
            'message' => trim($data['message']),
            'is_read' => false,
            'replied' => false,
        ]);

        $this->sendAcknowledgement($contact);

        return redirect()->route('contact.index')->with('status', 'Thank you for your message. We will get back to you soon.');
    }

    private function sendAcknowledgement(Contact $contact): void
    {
        $recipient = (string) config('services.contact_notification_email');
        if ($recipient !== '') {
            $this->sendMail(
                $recipient,
                'New Bealet website message',
                "New contact message from {$contact->name} ({$contact->email}):\n\n{$contact->message}",
                $contact
            );
        }
        $this->sendMail(
            $contact->email,
            'We received your message',
            "Hello {$contact->name},\n\nWe received your message and will get back to you as soon as possible.\n\nBealet Optical Center",
            $contact
        );
    }

    private function sendMail(string $recipient, string $subject, string $body, Contact $contact): void
    {
        try {
            Mail::to($recipient)->send(new PlainTextNotification($subject, $body));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Contact message email could not be delivered.', [
                'contact_id' => $contact->id,
                'recipient' => $recipient,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
