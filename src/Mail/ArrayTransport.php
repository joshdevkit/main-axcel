<?php

namespace Axcel\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Address;

class ArrayTransport implements TransportInterface
{
    protected static array $messages = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        // Create envelope if not provided
        if (!$envelope) {
            $envelope = new Envelope(new Address('test@example.com'), [new Address('test@example.com')]);
        }

        static::$messages[] = [
            'message' => $message,
            'envelope' => $envelope,
            'sent_at' => time()
        ];

        return new SentMessage($message, $envelope);
    }

    public static function getMessages(): array
    {
        return static::$messages;
    }

    public static function clear(): void
    {
        static::$messages = [];
    }

    public static function count(): int
    {
        return count(static::$messages);
    }

    public function __toString(): string
    {
        return 'array://';
    }
}
