<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

/**
 * @method static string make(string $value)
 * @method static bool check(string $value, string $hashedValue)
 * @method static bool needsRehash(string $hashedValue)
 *
 * @see \Axcel\Hashing\Hasher
 *
 * Facade for the hashing service.
 */
class Hash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'hash';
    }
}
