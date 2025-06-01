<?php

namespace Axcel\Database\Traits;

use Axcel\Facades\Hash;

/**
 * Trait HasAttributes
 * 
 * Provides support for attribute casting and accessors/mutators.
 */
trait HasAttributes
{
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        return method_exists($this, 'casts') ? $this->casts() : $this->casts;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if ($value === null) {
            return $value;
        }

        $casts = $this->getCasts();

        if (!isset($casts[$key])) {
            return $value;
        }

        $castType = $casts[$key];

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return json_decode($value, true);
            case 'object':
                return json_decode($value);
            case 'collection':
                return collect(json_decode($value, true));
            case 'date':
                return new \DateTime($value);
            case 'datetime':
                return new \DateTime($value);
            case 'timestamp':
                return strtotime($value);
            case 'hashed':
                return $value; // Hashed values remain unchanged when casting
            default:
                return $value;
        }
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // First check if the attribute exists
        if (!isset($this->attributes[$key]) && !array_key_exists($key, $this->attributes)) {
            // If it doesn't exist, check if we have a relationship
            if (method_exists($this, $key)) {
                return $this->$key();
            }
            return null;
        }

        $value = $this->attributes[$key];

        // Check for a custom getter method
        $getter = 'get' . ucfirst($key) . 'Attribute';

        if (method_exists($this, $getter)) {
            return $this->$getter($value);
        }

        // Apply casting
        return $this->castAttribute($key, $value);
    }

    /**
     * Determine if the given value is already hashed.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isPasswordHashed($value)
    {
        return (
            is_string($value) && (
                str_starts_with($value, '$2y') ||
                str_starts_with($value, '$2a') ||
                str_starts_with($value, '$argon2i') ||
                str_starts_with($value, '$argon2id')
            )
        );
    }

    /**
     * Process attribute before setting it on the model.
     * This method is called by the Model's setAttribute method.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function processAttributeBeforeSet($key, $value)
    {
        // Check for a custom setter method
        $setter = 'set' . ucfirst($key) . 'Attribute';

        if (method_exists($this, $setter)) {
            $this->$setter($value);
            return null; // Will be handled by the setter method
        }

        $casts = $this->getCasts();

        // Auto-hash password if it's not already hashed
        if (isset($casts[$key]) && $casts[$key] === 'hashed' && $value !== null) {
            // Check if the value is already hashed
            if (!$this->isPasswordHashed($value)) {
                $value = Hash::make($value);
            }
        }

        return $value;
    }
}
