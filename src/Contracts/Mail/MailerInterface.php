<?php

namespace Axcel\Contracts\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $content, array $options = []): bool;
    public function sendMailable(MailableInterface $mailable): bool;
    public function renderView(string $view, array $data = []): string;
}
