<?php

namespace App\Notifications;

use App\Models\AccountRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountRequestApproved extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AccountRequest $accountRequest,
        private readonly string $plaintextToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')."/invite/{$this->plaintextToken}";

        return (new MailMessage)
            ->subject('Your ArkWorkers account request was approved')
            ->greeting("Hi {$this->accountRequest->name},")
            ->line('Your request to join ArkWorkers has been approved.')
            ->action('Set up your account', $url)
            ->line('This link is valid for 7 days and can only be used once.');
    }
}
