<?php

namespace Axcel\Session;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\PhpBridgeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;

class SessionManager
{
    private Session $session;
    private array $config;
    private bool $started = false;

    public function __construct()
    {
        $this->config = config('session', []);
        $this->createSession();
    }

    private function createSession(): void
    {
        $options = [
            'cookie_lifetime' => $this->config['lifetime'] * 60,
            'cookie_path' => $this->config['path'],
            'cookie_domain' => $this->config['domain'],
            'cookie_secure' => $this->config['secure'],
            'cookie_httponly' => $this->config['http_only'],
            'cookie_samesite' => $this->config['same_site'],
            'name' => $this->config['cookie'],
            'gc_probability' => $this->config['gc_probability'],
            'gc_divisor' => $this->config['gc_divisor'],
            'gc_maxlifetime' => $this->config['lifetime'] * 60,
        ];
        if ($this->config['driver'] === 'file') {
            $savePath = $this->config['files'];
            if (!is_dir($savePath)) {
                mkdir($savePath, 0755, true); // Ensure the directory exists
            }
            $handler = new NativeFileSessionHandler($savePath);
            $storage = new NativeSessionStorage($options, $handler);
        } else {
            $storage = new PhpBridgeSessionStorage();
        }

        $this->session = new Session($storage);
    }

    public function start(): void
    {
        if ($this->started || $this->session->isStarted()) {
            return;
        }

        $this->session->start();
        $this->started = true;
    }

    public function get(string $key, $default = null)
    {
        $this->ensureStarted();
        return $this->session->get($key, $default);
    }

    public function put(string $key, $value): void
    {
        $this->ensureStarted();
        $this->session->set($key, $value);
    }

    public function push(string $key, $value): void
    {
        $array = $this->get($key, []);
        if (!is_array($array)) {
            $array = [];
        }
        $array[] = $value;
        $this->put($key, $array);
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return $this->session->has($key);
    }

    public function forget(string $key): void
    {
        $this->ensureStarted();
        $this->session->remove($key);
    }

    public function flush(): void
    {
        $this->ensureStarted();
        $this->session->clear();
    }

    public function flash(string $key, $value): void
    {
        $this->ensureStarted();
        $this->session->getFlashBag()->add($key, $value);
    }

    public function flashInput(array $input): void
    {
        foreach ($input as $key => $value) {
            $this->flash("input.{$key}", $value);
        }
    }

    public function reflash(): void
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();
        $flashes = $flashBag->peekAll();

        foreach ($flashes as $key => $messages) {
            foreach ($messages as $message) {
                $flashBag->add($key, $message);
            }
        }
    }

    public function keep(?array $keys = null): void
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();

        if ($keys === null) {
            $this->reflash();
            return;
        }

        foreach ($keys as $key) {
            if ($flashBag->has($key)) {
                $messages = $flashBag->peek($key);
                foreach ($messages as $message) {
                    $flashBag->add($key, $message);
                }
            }
        }
    }

    public function old(string $key, $default = null)
    {
        return $this->getOldInput($key, $default);
    }

    public function hasOldInput(?string $key = null): bool
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();

        if ($key === null) {
            return $flashBag->has('input');
        }

        return $flashBag->has("input.{$key}");
    }

    public function getOldInput(?string $key = null, $default = null)
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();

        if ($key === null) {
            $inputs = [];
            foreach ($flashBag->peek('input') as $inputKey => $inputValue) {
                if (strpos($inputKey, 'input.') === 0) {
                    $inputs[substr($inputKey, 6)] = $inputValue;
                }
            }
            return $inputs;
        }

        $messages = $flashBag->peek("input.{$key}");
        return !empty($messages) ? $messages[0] : $default;
    }

    public function ageFlashData(): void
    {
        // Symfony handles flash data aging automatically
        // This method exists for compatibility
    }

    public function token(): string
    {
        if (!$this->has('_token')) {
            $this->regenerateToken();
        }
        return $this->get('_token');
    }

    public function regenerateToken(): void
    {
        $this->put('_token', bin2hex(random_bytes(40)));
    }

    public function regenerate(bool $destroy = false): bool
    {
        $this->ensureStarted();
        return $this->session->migrate($destroy);
    }

    public function invalidate(): bool
    {
        $this->ensureStarted();
        $this->session->invalidate();
        return true;
    }

    public function getId(): string
    {
        $this->ensureStarted();
        return $this->session->getId();
    }

    public function setId(string $id): void
    {
        $this->ensureStarted();
        $this->session->setId($id);
    }

    public function getName(): string
    {
        $this->ensureStarted();
        return $this->session->getName();
    }

    public function setName(string $name): void
    {
        $this->ensureStarted();
        $this->session->setName($name);
    }

    public function save(): void
    {
        if ($this->session->isStarted()) {
            $this->session->save();
        }
    }

    public function isStarted(): bool
    {
        return $this->session->isStarted();
    }

    private function ensureStarted(): void
    {
        if (!$this->started && !$this->session->isStarted()) {
            $this->start();
        }
    }

    public function all(): array
    {
        $this->ensureStarted();
        return $this->session->all();
    }

    public function replace(array $attributes): void
    {
        $this->ensureStarted();
        $this->session->replace($attributes);
    }

    public function remove(string $key)
    {
        $this->ensureStarted();
        return $this->session->remove($key);
    }

    /**
     * Get the underlying Symfony session instance
     */
    public function getSymfonySession(): Session
    {
        return $this->session;
    }

    /**
     * Get flash messages for a given key
     */
    public function getFlashBag(): \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface
    {
        $this->ensureStarted();
        return $this->session->getFlashBag();
    }

    /**
     * Get flash messages for a given key
     */
    public function getFlashes(?string $key = null): array
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();

        return $key ? $flashBag->get($key) : $flashBag->all();
    }

    /**
     * Check if there are flash messages for a given key
     */
    public function hasFlashes(?string $key = null): bool
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();

        return $key ? $flashBag->has($key) : !empty($flashBag->peekAll());
    }

    /**
     * Peek at flash messages without removing them
     */
    public function peekFlashes(?string $key = null): array
    {
        $this->ensureStarted();
        $flashBag = $this->session->getFlashBag();

        return $key ? $flashBag->peek($key) : $flashBag->peekAll();
    }
}
