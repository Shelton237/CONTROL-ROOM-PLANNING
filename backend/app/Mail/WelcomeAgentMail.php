<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeAgentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $loginEmail,
        public string $password,
        public string $loginUrl,
    ) {
    }

    public function build()
    {
        return $this->subject('Votre accès au Planning Control Room')
            ->text('mail.welcome-agent', [
                'employeeName' => $this->employeeName,
                'loginEmail' => $this->loginEmail,
                'password' => $this->password,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
