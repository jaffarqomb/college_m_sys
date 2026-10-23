<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New website contact message')
            ->greeting('New contact message')
            ->line("**{$this->contactMessage->name}** <{$this->contactMessage->email}>")
            ->line($this->contactMessage->subject ? "Subject: {$this->contactMessage->subject}" : '')
            ->line($this->contactMessage->message)
            ->action('Open admin panel', route('filament.admin.pages.dashboard'));
    }
}
