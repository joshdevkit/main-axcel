<?php

namespace Axcel\Facades;

/**
 * Class FacadeResolver
 * 
 * Handles automatic importing of facade classes in views
 */
class FacadesResolver
{
    /**
     * List of facades that will be automatically imported into views
     * 
     * @var array
     */
    protected static $facades = [
        'Route' => Route::class,
        'Auth' => Auth::class,
        'Session' => Session::class,
        'DB' => DB::class,
    ];

    /**
     * Register all facade aliases
     * 
     * @return void
     */
    public static function register(): void
    {
        foreach (static::$facades as $alias => $facade) {
            if (!class_exists($alias)) {
                class_alias($facade, $alias);
            }
        }
    }

    /**
     * Add a new facade to the list
     * 
     * @param string $alias
     * @param string $facade
     * @return void
     */
    public static function addFacade(string $alias, string $facade): void
    {
        static::$facades[$alias] = $facade;
    }

    /**
     * Get all registered facades
     * 
     * @return array
     */
    public static function getFacades(): array
    {
        return static::$facades;
    }
}
