<?php

namespace Axcel\Support;

use Axcel\Foundation\Application;

abstract class Facade
{
    protected static Application $app;

    public static function setApplication(Application $app): void
    {
        static::$app = $app;
    }

    /**
     * Get the component registered in the container.
     */
    protected static function getFacadeRoot()
    {
        return static::$app->get(static::getFacadeAccessor());
    }

    /**
     * Handle dynamic static method calls to the object.
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }

    /**
     * Each facade must implement this to return its accessor key.
     */
    protected static abstract function getFacadeAccessor();
}
