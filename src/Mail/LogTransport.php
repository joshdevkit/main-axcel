<?php

namespace Axcel\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LogTransport implements TransportInterface
{
    protected string $logPath;

    public function __construct(?string $logPath = null)
    {
        // Use provided path or default to storage/logs
        $this->logPath = $logPath ?: $this->getDefaultLogPath();

        // Ensure the logs directory exists
        $this->ensureLogDirectoryExists();
    }

    protected function getDefaultLogPath(): string
    {
        // Get the application instance to access storage path
        $app = app();
        $storagePath = $app->storagePath('logs');

        return $storagePath . '/mail-' . date('Y-m-d') . '.log';
    }

    protected function ensureLogDirectoryExists(): void
    {
        $directory = dirname($this->logPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Mail sent:\n";

        if ($envelope) {
            $recipients = array_map(fn($addr) => $addr->getAddress(), $envelope->getRecipients());
            $logMessage .= "To: " . implode(', ', $recipients) . "\n";
            $logMessage .= "From: " . $envelope->getSender()->getAddress() . "\n";
        }

        // If it's an Email object, we can access headers and body parts
        if ($message instanceof Email) {
            $subject = $message->getSubject();
            $logMessage .= "Subject: " . ($subject ?: '(no subject)') . "\n";
            $logMessage .= "Body: " . $message->getTextBody() . "\n"; // or getHtmlBody() if needed
        } else {
            // fallback for RawMessage
            $logMessage .= "Body: " . $message->toString() . "\n";
        }

        $logMessage .= str_repeat('-', 80) . "\n\n";

        $this->writeToLogFile($logMessage);

        if (!$envelope) {
            $envelope = new Envelope(new Address('test@example.com'), [new Address('test@example.com')]);
        }

        return new SentMessage($message, $envelope);
    }

    protected function writeToLogFile(string $message): void
    {
        try {
            file_put_contents($this->logPath, $message, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Fallback to error_log if file writing fails
            error_log("Failed to write mail log to file: " . $e->getMessage());
            error_log("Mail log: " . $message);
        }
    }

    public function __toString(): string
    {
        return 'log://';
    }
}
