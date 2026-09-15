<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class VehicleDocumentsNeedAttention extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, \App\Models\Vehicle>  $vehicles
     */
    public function __construct(
        private readonly Collection $vehicles,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Vehicle documents need attention')
            ->greeting("Hi {$notifiable->name},");

        foreach ($this->vehicles as $vehicle) {
            $label = $vehicle->name ?: $vehicle->plate_number;

            foreach ($vehicle->expiredDocuments() as $type => $date) {
                $mail->line("{$label}: {$type} expired on {$date}.");
            }

            foreach ($vehicle->expiringSoonDocuments() as $type => $date) {
                $mail->line("{$label}: {$type} expires on {$date}.");
            }
        }

        return $mail->action('Review vehicles', rtrim(config('app.frontend_url'), '/').'/vehicles');
    }
}
