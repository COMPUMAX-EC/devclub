<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeAdmin extends Notification implements ShouldQueue
{

	use Queueable;

	public function __construct(public string $loginUrl, public string $tempPassword)
	{
		//$this->afterCommit();
	}

	public function via($notifiable): array
	{
		return ['mail'];
	}

	public function toMail($notifiable): MailMessage
	{
		// Si manejas locales por usuario, puedes setearlo al notificar:
		// $user->notify((new WelcomeAdmin(...))->locale($user->locale ?? app()->getLocale()));

		return (new MailMessage)
						->subject('Bienvenido(a) — Acceso Administrativo')
						->view('emails.admin.welcome', [
							'user'		   => $notifiable,
							'loginUrl'	   => $this->loginUrl,
							'tempPassword' => $this->tempPassword,
		]);
	}
}
