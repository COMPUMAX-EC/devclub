<?php
namespace App\Notifications\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordCustomer extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url, public int $minutes = 30) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Restablecer contraseña')
            ->markdown('emails.customer.reset-password', [
                'user' => $notifiable,
                'url' => $this->url,
                'minutes' => $this->minutes,
            ]);
    }
}
