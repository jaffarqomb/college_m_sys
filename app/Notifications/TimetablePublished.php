<?php

namespace App\Notifications;

use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TimetablePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AcademicTerm $term) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Timetable published: '.$this->term->name)
            ->greeting('The timetable is published')
            ->line("The class timetable for **{$this->term->name}** is now final.")
            ->line('Open your portal to confirm your weekly schedule.')
            ->action('View the timetable', $this->dashboardUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'AutoCollege: The %s timetable is published. Confirm your schedule on the portal.',
            $this->term->name,
        );
    }

    private function dashboardUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->currentTeam !== null
            ? route('dashboard', ['current_team' => $notifiable->currentTeam->slug])
            : url('/');
    }
}
