<?php

final class UserdbLocalMailer implements Nette\Mail\Mailer
{
    public function __construct(private string $directory) {
    }

    public function send(Nette\Mail\Message $mail): void {
        Nette\Utils\FileSystem::write($this->directory . '/' . bin2hex(random_bytes(12)) . '.eml', $mail->generateMessage(), 0600);
    }
}
