<?php

namespace Axcel\Support;

class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $path . '/.env';

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }

            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Set in $_ENV and $_SERVER
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                putenv("$name=$value");

                self::$variables[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        // Check $_ENV first
        if (isset($_ENV[$key])) {
            return self::parseValue($_ENV[$key]);
        }

        // Check $_SERVER
        if (isset($_SERVER[$key])) {
            return self::parseValue($_SERVER[$key]);
        }

        // Check our loaded variables
        if (isset(self::$variables[$key])) {
            return self::parseValue(self::$variables[$key]);
        }

        // Check system environment
        $value = getenv($key);
        if ($value !== false) {
            return self::parseValue($value);
        }

        return $default;
    }

    private static function parseValue($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        // Handle boolean values
        if (strtolower($value) === 'true') {
            return true;
        }

        if (strtolower($value) === 'false') {
            return false;
        }

        // Handle null
        if (strtolower($value) === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function all(): array
    {
        return self::$variables;
    }
}
