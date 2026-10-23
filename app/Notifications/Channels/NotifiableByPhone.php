<?php

namespace App\Notifications\Channels;

/**
 * Marks a notifiable as addressable by the SMS channel via its phone number.
 */
interface NotifiableByPhone
{
    /**
     * The phone number to deliver SMS messages to, or null when unavailable.
     */
    public function routeNotificationForSms(?object $notification = null): ?string;
}
