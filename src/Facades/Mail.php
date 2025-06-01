<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

/**
 * @method static bool send(string|MailableInterface $to, string $subject = '', string $viewOrContent = '', array $dataOrOptions = [], array $options = [])
 * @method static bool sendMailable(MailableInterface $mailable)
 */
class Mail extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'mail';
    }
}
