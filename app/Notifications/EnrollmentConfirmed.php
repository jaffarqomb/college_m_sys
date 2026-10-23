<?php

namespace App\Notifications;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enrollment $enrollment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $section = $this->enrollment->section->fullCode();
        $course = $this->enrollment->section->course->title;

        return (new MailMessage)
            ->subject('Enrollment confirmed: '.$section)
            ->greeting('Enrollment confirmed')
            ->line("You are enrolled in **{$section} — {$course}** for {$this->enrollment->section->term->name}.")
            ->action('View your enrollments', $this->dashboardUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'AutoCollege: You are enrolled in %s for %s.',
            $this->enrollment->section->fullCode(),
            $this->enrollment->section->term->name,
        );
    }

    private function dashboardUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->currentTeam !== null
            ? route('dashboard', ['current_team' => $notifiable->currentTeam->slug])
            : url('/');
    }
}
