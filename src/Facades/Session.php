<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

class Session extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'session';
    }
}
