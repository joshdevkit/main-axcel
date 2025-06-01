<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

class Config extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}
