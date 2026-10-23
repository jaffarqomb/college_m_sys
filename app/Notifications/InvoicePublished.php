<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

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
            ->subject('Your invoice '.$this->invoice->number.' is ready')
            ->greeting('Invoice ready')
            ->line("**{$this->invoice->number}** for {$this->invoice->term->name} totals ".number_format((float) $this->invoice->total, 3).' KWD.')
            ->line('The due date is '.$this->invoice->due_date->format('M j, Y').'.')
            ->action('View and pay your invoices', $this->dashboardUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'AutoCollege: Invoice %s for %s is due %s. Pay from your portal.',
            $this->invoice->number,
            $this->invoice->term->name,
            $this->invoice->due_date->format('M j, Y'),
        );
    }

    private function dashboardUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->currentTeam !== null
            ? route('dashboard', ['current_team' => $notifiable->currentTeam->slug])
            : url('/');
    }
}
