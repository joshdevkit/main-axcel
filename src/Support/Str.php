<?php

namespace Axcel\Support;

use voku\helper\ASCII;

class Str
{
    /**
     * Cached irregular plural forms
     * 
     * @var array
     */
    protected static $pluralCache = [];

    /**
     * Irregular word forms mapping (singular => plural)
     * 
     * @var array
     */
    protected static $irregular = [
        'person' => 'persons',
        'man' => 'men',
        'child' => 'children',
        'woman' => 'women',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'goose' => 'geese',
        'mouse' => 'mice',
        'ox' => 'oxen',
    ];

    /**
     * Words that are always in plural form
     * 
     * @var array
     */
    protected static $uncountable = [
        'equipment',
        'information',
        'rice',
        'money',
        'species',
        'series',
        'fish',
        'sheep',
        'data',
    ];

    /**
     * Words ending with "o" that take "es" in plural form
     * 
     * @var array
     */
    protected static $oExceptions = [
        'hero',
        'potato',
        'tomato',
        'tornado',
        'torpedo',
        'veto',
        'echo',
        'buffalo',
        'mosquito',
        'volcano',
    ];

    public static function of(string $value): StrObject
    {
        return new StrObject($value);
    }

    /**
     * Generate a random string of specified length.
     *
     * @param  int  $length
     * @return string
     */
    public static function random($length = 16)
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytes = random_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Generate a UUID (v4).
     *
     * @return string
     */
    public static function uuid()
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Convert a string to snake case.
     *
     * @param  string  $value
     * @return string
     */
    public static function snake($value)
    {
        $value = preg_replace('/([A-Z])/', '_$1', $value);
        return strtolower(ltrim($value, '_'));
    }

    /**
     * Convert a string to camel case.
     *
     * @param  string  $value
     * @return string
     */
    public static function camel($value)
    {
        return lcfirst(static::studly($value));
    }

    /**
     * Convert a string to studly caps case.
     *
     * @param  string  $value
     * @return string
     */
    public static function studly($value)
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return str_replace(' ', '', $value);
    }

    public static function pluralStudly(string $value): string
    {
        return self::studly(self::plural(self::snake($value)));
    }


    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles)
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transliterate a UTF-8 value to ASCII.
     *
     * @param  string  $value
     * @param  string  $language
     * @return string
     */
    public static function ascii($value, $language = 'en')
    {
        return ASCII::to_ascii((string) $value, $language, replace_single_chars_only: false);
    }


    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param  string  $title
     * @param  string  $separator
     * @param  string|null  $language
     * @param  array<string, string>  $dictionary
     * @return string
     */
    public static function slug($title, $separator = '-', $language = 'en', $dictionary = ['@' => 'at'])
    {
        $title = $language ? static::ascii($title, $language) : $title;

        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace dictionary words
        foreach ($dictionary as $key => $value) {
            $dictionary[$key] = $separator . $value . $separator;
        }

        $title = str_replace(array_keys($dictionary), array_values($dictionary), $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function endsWith($haystack, $needles)
    {
        if (!is_array($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && substr($haystack, -strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert the given string to lower-case.
     *
     * @param  string  $value
     * @return string
     */
    public static function lower($value)
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Get the plural form of an English word.
     *
     * @param  string  $value
     * @return string
     */
    public static function plural($value)
    {
        if (isset(static::$pluralCache[$value])) {
            return static::$pluralCache[$value];
        }

        $uncountable = in_array(strtolower($value), static::$uncountable);

        if ($uncountable) {
            return $value;
        }

        $irregular = static::$irregular[strtolower($value)] ?? false;

        if ($irregular) {
            return static::$pluralCache[$value] = $irregular;
        }

        $plural = $value;
        $lower = strtolower($value);

        // Check the rules for pluralization
        if (preg_match('/(s|x|z|ch|sh)$/i', $value)) {
            $plural = $value . 'es';
        } elseif (preg_match('/([^aeiou])y$/i', $value)) {
            $plural = substr($value, 0, -1) . 'ies';
        } elseif (preg_match('/o$/i', $value) && in_array($lower, static::$oExceptions)) {
            // Only apply 'o' + 'es' rule if the word is in our exceptions list
            $plural = $value . 'es';
        } else {
            $plural = $value . 's';
        }

        return static::$pluralCache[$value] = $plural;
    }

    /**
     * Convert class name to table name
     * 
     * @param string $className
     * @return string
     */
    public static function tableize($className)
    {
        // Get the base class name if namespace exists
        $baseName = class_basename($className);

        // Convert to snake case
        $snake = static::snake($baseName);

        // Pluralize
        return static::plural($snake);
    }
}
