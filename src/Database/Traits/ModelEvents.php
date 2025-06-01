<?php

namespace Axcel\Database\Traits;

use Axcel\Events\EventDispatcher;

trait ModelEvents
{
    /**
     * The event map for the model.
     *
     * @var array
     */
    protected static $eventMap = [];

    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    public static function bootModelEvents()
    {
        static::$eventMap = [
            'creating' => 'Axcel\Database\Events\CreatingEvent',
            'created' => 'Axcel\Database\Events\CreatedEvent',
            'updating' => 'Axcel\Database\Events\UpdatingEvent',
            'updated' => 'Axcel\Database\Events\UpdatedEvent',
            'deleting' => 'Axcel\Database\Events\DeletingEvent',
            'deleted' => 'Axcel\Database\Events\DeletedEvent',
        ];
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @return void
     */
    protected static function registerModelEvent($event, $callback)
    {
        $name = static::class;

        $eventName = "{$name}.{$event}";

        EventDispatcher::listen($eventName, $callback);
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @return mixed
     */
    protected function fireModelEvent($event)
    {
        $name = static::class;

        $eventName = "{$name}.{$event}";

        $halt = $event === 'creating' || $event === 'updating' || $event === 'deleting';

        $result = EventDispatcher::dispatch($eventName, $this);

        if ($halt && $result === false) {
            return false;
        }
    }

    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function creating($callback)
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function created($callback)
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register an updating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function updating($callback)
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function updated($callback)
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function deleting($callback)
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function deleted($callback)
    {
        static::registerModelEvent('deleted', $callback);
    }
}
