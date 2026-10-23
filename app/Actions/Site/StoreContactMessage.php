<?php

namespace App\Actions\Site;

use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Support\Facades\Notification;

/**
 * Persists a contact form submission and notifies the admissions team.
 */
class StoreContactMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ContactMessage
    {
        $contactMessage = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'is_read' => false,
        ]);

        Notification::route('mail', config('site.contact.email'))
            ->notify(new ContactMessageReceived($contactMessage));

        return $contactMessage;
    }
}
