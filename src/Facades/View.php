<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

class View extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'view';
    }
}
