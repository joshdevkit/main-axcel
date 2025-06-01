<?php
// Axcel/Mail/MailManager.php
namespace Axcel\Mail;

use Axcel\Contracts\Mail\MailableInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Axcel\Contracts\Mail\MailerInterface;
use Axcel\View\ViewFactory;

class MailManager implements MailerInterface
{
    protected array $config;
    protected Mailer $mailer;
    protected ViewFactory $view;
    protected array $globalFrom = [];
    protected array $globalReplyTo = [];

    public function __construct(array $config, ViewFactory $view)
    {
        $this->config = $config;
        $this->view = $view;
        $this->setupMailer();
        $this->setupGlobalSettings();
    }

    protected function setupMailer(): void
    {
        $transport = $this->createTransport();
        $this->mailer = new Mailer($transport);
    }

    protected function createTransport()
    {
        $driver = $this->config['default'] ?? 'smtp';
        $config = $this->config['mailers'][$driver] ?? [];

        switch ($driver) {
            case 'smtp':
                return $this->createSmtpTransport($config);
            case 'ses':
                return $this->createSesTransport($config);
            case 'mailgun':
                return $this->createMailgunTransport($config);
            case 'postmark':
                return $this->createPostmarkTransport($config);
            case 'sendmail':
                return Transport::fromDsn('sendmail://default');
            case 'log':
                return $this->createLogTransport($config);
            case 'array':
                return new ArrayTransport();
            default:
                throw new \InvalidArgumentException("Unsupported mail driver: {$driver}");
        }
    }

    protected function createSmtpTransport(array $config)
    {
        $dsn = sprintf(
            'smtp://%s:%s@%s:%d',
            $config['username'] ?? '',
            $config['password'] ?? '',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 587
        );

        if (isset($config['encryption'])) {
            $dsn .= '?encryption=' . $config['encryption'];
        }

        return Transport::fromDsn($dsn);
    }

    protected function createSesTransport(array $config)
    {
        $dsn = sprintf(
            'ses+smtp://%s:%s@default?region=%s',
            $config['key'] ?? '',
            $config['secret'] ?? '',
            $config['region'] ?? 'us-east-1'
        );

        return Transport::fromDsn($dsn);
    }

    protected function createMailgunTransport(array $config)
    {
        $dsn = sprintf(
            'mailgun+smtp://%s:%s@default?domain=%s',
            $config['username'] ?? '',
            $config['password'] ?? '',
            $config['domain'] ?? ''
        );

        return Transport::fromDsn($dsn);
    }

    protected function createPostmarkTransport(array $config)
    {
        $dsn = sprintf(
            'postmark+smtp://%s@default',
            $config['token'] ?? ''
        );

        return Transport::fromDsn($dsn);
    }

    protected function createLogTransport(array $config)
    {
        $logPath = $config['path'] ?? null;
        return new LogTransport($logPath);
    }

    protected function setupGlobalSettings(): void
    {
        if (isset($this->config['from'])) {
            $this->globalFrom = $this->config['from'];
        }

        if (isset($this->config['reply_to'])) {
            $this->globalReplyTo = $this->config['reply_to'];
        }
    }

    public function send(mixed $to, string $subject = '', string $viewOrContent = '', array $dataOrOptions = [], array $options = []): bool
    {
        // If a Mailable is passed as $to, delegate to sendMailable
        if ($to instanceof MailableInterface) {
            return $this->sendMailable($to);
        }

        // View/content mode
        try {
            if (is_array($dataOrOptions)) {
                $content = $this->renderView($viewOrContent, $dataOrOptions);
            } else {
                $content = $viewOrContent;
                $options = $dataOrOptions; // allow 4-param version: to, subject, content, options
            }

            $email = $this->buildEmail($to, $subject, $content, $options);
            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            error_log("Mail send failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendMailable(MailableInterface $mailable): bool
    {
        try {
            $email = $mailable->build($this);
            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            error_log("Mailable send failed: " . $e->getMessage());
            return false;
        }
    }

    protected function buildEmail(string $to, string $subject, string $content, array $options = []): Email
    {
        $email = new Email();

        // Set basic properties
        $email->subject($subject);

        // Set recipients
        if (is_string($to)) {
            $email->to($to);
        } elseif (is_array($to)) {
            foreach ($to as $address) {
                $email->addTo($address);
            }
        }

        // Set content
        if (isset($options['html']) && $options['html']) {
            $email->html($content);
        } else {
            $email->text($content);
        }

        // Set from address
        $from = $options['from'] ?? $this->globalFrom;
        if ($from) {
            if (is_array($from) && isset($from['address'])) {
                $email->from(new Address($from['address'], $from['name'] ?? ''));
            } elseif (is_string($from)) {
                $email->from($from);
            }
        }

        // Set reply-to
        $replyTo = $options['reply_to'] ?? $this->globalReplyTo;
        if ($replyTo) {
            if (is_array($replyTo) && isset($replyTo['address'])) {
                $email->replyTo(new Address($replyTo['address'], $replyTo['name'] ?? ''));
            } elseif (is_string($replyTo)) {
                $email->replyTo($replyTo);
            }
        }

        // Add CC and BCC
        if (isset($options['cc'])) {
            $email->cc($options['cc']);
        }

        if (isset($options['bcc'])) {
            $email->bcc($options['bcc']);
        }

        // Add attachments
        if (isset($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (is_string($attachment)) {
                    $email->attachFromPath($attachment);
                } elseif (is_array($attachment)) {
                    $email->attachFromPath(
                        $attachment['path'],
                        $attachment['name'] ?? null,
                        $attachment['mime'] ?? null
                    );
                }
            }
        }

        return $email;
    }

    public function renderView(string $view, array $data = []): string
    {
        return $this->view->make($view, $data);
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
