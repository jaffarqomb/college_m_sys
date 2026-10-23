<?php

namespace App\Notifications;

use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradesUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CourseSection $section) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->section->fullCode();
        $course = $this->section->course->title;

        return (new MailMessage)
            ->subject('Scores entered for '.$code)
            ->greeting('Your scores were updated')
            ->line("Assessment scores were entered for **{$code} — {$course}**.")
            ->line('Open your portal gradebook to see the details and your running grade.')
            ->action('Open your gradebook', $this->dashboardUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'AutoCollege: New scores were entered for %s. Check your portal gradebook.',
            $this->section->fullCode(),
        );
    }

    private function dashboardUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->currentTeam !== null
            ? route('dashboard', ['current_team' => $notifiable->currentTeam->slug])
            : url('/');
    }
}
