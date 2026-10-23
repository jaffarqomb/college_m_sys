<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Config-driven SMS channel.
 *
 * Resolves the recipient from the notifiable's phone number (`NotifiableByPhone')
 * and the message body from the notification's `toSms()` method. Sending is
 * gated by config("services.sms.driver"): when no driver is configured
 * nothing is sent and mail notifications still carry the message on their
 * own. The built-in "log" driver writes to the `sms` log channel; a provider
 * package's driver can be added to the match when installed.
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $driver = config('services.sms.driver');
        $phoneNumber = $notifiable instanceof NotifiableByPhone
            ? $notifiable->routeNotificationForSms($notification)
            : null;
        $message = method_exists($notification, 'toSms')
            ? $notification->toSms($notifiable)
            : null;

        if ($driver === null || $driver === '' || $phoneNumber === null || $message === null) {
            return;
        }

        match ($driver) {
            'log' => Log::channel('sms')->info('SMS to '.$phoneNumber, [
                'to' => $phoneNumber,
                'from' => config('services.sms.from'),
                'message' => $message,
            ]),
            default => Log::channel('sms')->warning("Unknown SMS driver \"$driver\"; message not sent.", [
                'to' => $phoneNumber,
                'message' => $message,
            ]),
        };
    }
}
