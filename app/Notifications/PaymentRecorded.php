<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRecorded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'sms'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->payment->invoice;
        $balance = $invoice->balance();

        return (new MailMessage)
            ->subject('Payment received for '.$invoice->number)
            ->greeting('Payment received')
            ->line('We received '.number_format((float) $this->payment->amount, 3).' KWD against **'.$invoice->number.'**.')
            ->line($balance > 0.001
                ? 'Your remaining balance is '.number_format($balance, 3).' KWD.'
                : 'The invoice is now fully paid — thank you.')
            ->action('View your invoices', $this->dashboardUrl($notifiable));
    }

    public function toSms(object $notifiable): string
    {
        $invoice = $this->payment->invoice;
        $balance = $invoice->balance();

        return sprintf(
            'AutoCollege: %s received for %s. %s',
            number_format((float) $this->payment->amount, 3).' KWD',
            $invoice->number,
            $balance > 0.001
                ? 'Balance: '.number_format($balance, 3).' KWD.'
                : 'Fully paid — thank you.',
        );
    }

    private function dashboardUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->currentTeam !== null
            ? route('dashboard', ['current_team' => $notifiable->currentTeam->slug])
            : url('/');
    }
}
