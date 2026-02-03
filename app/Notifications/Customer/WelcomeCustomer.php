<?php

namespace App\Notifications\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeCustomer extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $loginUrl,
        public string $tempPassword
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido(a) — Acceso a tu cuenta')
            ->view('emails.welcome_customer', [
                'user'         => $notifiable,
                'loginUrl'     => $this->loginUrl,
                'tempPassword' => $this->tempPassword,
            ]);
    }
}
