<?php

namespace Axcel\Mail;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Axcel\Contracts\Mail\MailableInterface;

abstract class Mailable implements MailableInterface
{
    protected string $subject = '';
    protected string $view = '';
    protected array $data = [];
    protected array $to = [];
    protected array $cc = [];
    protected array $bcc = [];
    protected array $from = [];
    protected array $replyTo = [];
    protected array $attachments = [];

    public function to(string|array $address): self
    {
        $this->to = is_array($address) ? $address : [$address];
        return $this;
    }

    public function cc(string|array $address): self
    {
        $this->cc = is_array($address) ? $address : [$address];
        return $this;
    }

    public function bcc(string|array $address): self
    {
        $this->bcc = is_array($address) ? $address : [$address];
        return $this;
    }

    public function from(string $address, string $name = ''): self
    {
        $this->from = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function replyTo(string $address, string $name = ''): self
    {
        $this->replyTo = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function view(string $view, array $data = []): self
    {
        $this->view = $view;
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function with(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
            'mime' => $mime
        ];
        return $this;
    }

    public function build(MailManager $mailer): Email
    {
        // Allow the mailable to configure itself
        $this->configure();

        $email = new Email();

        // Set subject
        $email->subject($this->subject);

        // Set recipients
        foreach ($this->to as $address) {
            $email->addTo($address);
        }

        foreach ($this->cc as $address) {
            $email->addCc($address);
        }

        foreach ($this->bcc as $address) {
            $email->addBcc($address);
        }

        // Set from
        if ($this->from) {
            $email->from(new Address($this->from['address'], $this->from['name']));
        }

        // Set reply-to
        if ($this->replyTo) {
            $email->replyTo(new Address($this->replyTo['address'], $this->replyTo['name']));
        }

        // Render and set content
        if ($this->view) {
            $content = $mailer->renderView($this->view, $this->data);
            $email->html($content);
        }

        // Add attachments
        foreach ($this->attachments as $attachment) {
            $email->attachFromPath(
                $attachment['path'],
                $attachment['name'],
                $attachment['mime']
            );
        }

        return $email;
    }

    abstract protected function configure(): void;
}
