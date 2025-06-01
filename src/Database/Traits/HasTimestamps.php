<?php

namespace Axcel\Database\Traits;

/**
 * Trait HasTimestamps
 * 
 * Provides support for auto-updating timestamps.
 */
trait HasTimestamps
{

    /**
     * Model Create at
     */
    const CREATED_AT = 'created_at';

    /**
     * Model Updated at
     */
    const UPDATED_AT = 'updated_at';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    protected $timestamps = true;

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch()
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    public function updateTimestamps()
    {
        $time = $this->freshTimestamp();

        if (!$this->isDirty(static::UPDATED_AT) && $this->usesTimestamps()) {
            $this->setUpdatedAt($time);
        }

        if (!$this->exists && !$this->isDirty(static::CREATED_AT) && $this->usesTimestamps()) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return string
     */
    public function freshTimestamp()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Set the value of the "created at" attribute.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function setCreatedAt($value)
    {
        $this->{static::CREATED_AT} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     *
     * @param  mixed  $value
     * @return $this
     */
    public function setUpdatedAt($value)
    {
        $this->{static::UPDATED_AT} = $value;

        return $this;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return $this->createdAt ?? 'created_at';
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return $this->updatedAt ?? 'updated_at';
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps()
    {
        return property_exists($this, 'timestamps') ? $this->timestamps : true;
    }
}
