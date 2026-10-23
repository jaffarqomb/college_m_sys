<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sectionCode,
        public float $percentage,
        public float $threshold,
    ) {}

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
            ->subject('Attendance below '.$this->threshold.'% in '.$this->sectionCode)
            ->greeting('Attendance warning')
            ->line("Your attendance in **{$this->sectionCode}** is {$this->percentage}%, below the {$this->threshold}% threshold.")
            ->line('Please contact your section instructor to plan catching up.')
            ->action('Open your portal', $this->dashboardUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'AutoCollege: Your attendance in %s is %s%%, below the %s%% threshold.',
            $this->sectionCode,
            $this->percentage,
            $this->threshold,
        );
    }

    private function dashboardUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->currentTeam !== null
            ? route('dashboard', ['current_team' => $notifiable->currentTeam->slug])
            : url('/');
    }
}
