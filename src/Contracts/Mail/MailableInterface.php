<?php

namespace Axcel\Contracts\Mail;

use Symfony\Component\Mime\Email;
use Axcel\Mail\MailManager;

interface MailableInterface
{
    public function build(MailManager $mailer): Email;
}
