<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 2026-09-15 sign-up redesign: a worker sets their own password
 * at sign-up time, so approval no longer needs to hand out an invite
 * token, it just lifts the login block (User::email_verified_at).
 * This replaced AccountRequestApproved for new sign-ups, that class
 * still exists and still works for the older invite-link flow, kept
 * for now rather than removed, see BUILD-PLAN.md.
 */
class SignUpApproved extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $user,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/login';

        return (new MailMessage)
            ->subject('Your ArkWorkers account has been approved')
            ->greeting("Hi {$this->user->name},")
            ->line('Your ArkWorkers account has been approved. You can log in now with the email and password you signed up with.')
            ->action('Log in', $url);
    }
}
