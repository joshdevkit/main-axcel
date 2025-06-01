<?php

namespace Axcel\Contracts\Auth;

use Axcel\Session\SessionManager;
use Axcel\Hashing\BcryptHasher;
use Axcel\Database\Model;
use Axcel\Facades\DB;

class AuthManager implements AuditTrailing
{
    private SessionManager $session;
    private BcryptHasher $hasher;
    private ?string $userModel = null;
    private ?Model $user = null;

    public function __construct(SessionManager $session, BcryptHasher $hasher, string $userModel = 'App\\Models\\User')
    {
        $this->session = $session;
        $this->hasher = $hasher;
        $this->userModel = $userModel;
    }

    /**
     * Attempt to authenticate a user
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if ($user && $this->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application
     */
    public function login(Model $user, bool $remember = false): void
    {
        $this->updateSession($user->id);

        if ($remember) {
            $this->createRememberToken($user);
        }

        $this->setUser($user);
        $this->fireLoginEvent($user);
    }

    /**
     * Log a user into the application without sessions or cookies
     */
    public function once(array $credentials): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if ($user && $this->validateCredentials($user, $credentials)) {
            $this->setUser($user);
            return true;
        }

        return false;
    }

    /**
     * Log the user out of the application
     */
    public function logout(): void
    {
        $user = $this->user();

        $this->clearUserDataFromStorage();

        if ($user) {
            $this->cycleRememberToken($user);
        }

        $this->user = null;
        $this->fireLogoutEvent($user);
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?Model
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->session->get($this->getAuthIdentifierName());

        if ($id === null || $id === '') {
            return null;
        }

        $this->user = $this->retrieveById($id);

        // Ensure we return null if retrieveById didn't find a user
        return $this->user ?: null;
    }

    /**
     * Get the ID for the currently authenticated user
     */
    public function id()
    {
        if ($user = $this->user()) {
            return $user->id;
        }

        return $this->session->get($this->getAuthIdentifierName());
    }

    /**
     * Determine if the current user is authenticated
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Determine if the current user is a guest
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Set the current user
     */
    public function setUser(Model $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Validate a user's credentials
     */
    public function validate(array $credentials): bool
    {
        $user = $this->retrieveByCredentials($credentials);
        return $user && $this->validateCredentials($user, $credentials);
    }

    /**
     * Retrieve a user by their unique identifier
     */
    protected function retrieveById($identifier): ?Model
    {
        if (!$this->userModel || !class_exists($this->userModel)) {
            return null;
        }

        return $this->userModel::find($identifier);
    }

    /**
     * Retrieve a user by the given credentials
     */
    protected function retrieveByCredentials(array $credentials): ?Model
    {
        if (!$this->userModel || !class_exists($this->userModel)) {
            return null;
        }

        $credentials = array_filter(
            $credentials,
            fn($key) => !str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return null;
        }

        $query = $this->userModel::query();

        foreach ($credentials as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * Validate the user against the given credentials
     */
    protected function validateCredentials(Model $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;

        if (!$plain) {
            return false;
        }

        return $this->hasher->check($plain, $user->password);
    }

    /**
     * Update the session with the given ID
     */
    protected function updateSession($id): void
    {
        $this->session->put($this->getAuthIdentifierName(), $id);
        $this->session->regenerate(true);
    }

    /**
     * Clear the user data from storage
     */
    protected function clearUserDataFromStorage(): void
    {
        $this->session->forget($this->getAuthIdentifierName());
    }

    /**
     * Create a remember token for the user
     */
    protected function createRememberToken(Model $user): void
    {
        $token = $this->createRememberTokenValue();

        if (method_exists($user, 'setRememberToken')) {
            $user->setRememberToken($token);
            $user->save();
        }

        $this->queueRecallerCookie($user, $token);
    }

    /**
     * Create a new remember token value
     */
    protected function createRememberTokenValue(): string
    {
        return hash_hmac('sha256', str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 5)), 'remember_token_key');
    }

    /**
     * Queue the recaller cookie
     */
    protected function queueRecallerCookie(Model $user, string $token): void
    {
        $value = $user->id . '|' . $token . '|' . $user->password;

        setcookie(
            $this->getRecallerName(),
            base64_encode($value),
            [
                'expires' => time() + (60 * 60 * 24 * 365 * 5),
                'path' => '/',
                'domain' => null,
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Cycle the remember token for the user
     */
    protected function cycleRememberToken(Model $user): void
    {
        if (method_exists($user, 'setRememberToken')) {
            $user->setRememberToken($this->createRememberTokenValue());
            $user->save();
        }
    }

    /**
     * Get the name of the identifier used to store in session
     */
    protected function getAuthIdentifierName(): string
    {
        return 'login_' . sha1(static::class);
    }

    /**
     * Get the name of the remember me cookie
     */
    protected function getRecallerName(): string
    {
        return 'remember_' . sha1(static::class);
    }

    protected function fireLoginEvent(Model $user): void
    {
        $this->log(
            'login',
            "{$user->name} has successfully logged in to the system.",
            $user->id
        );
    }

    protected function fireLogoutEvent(?Model $user): void
    {
        if ($user) {
            $this->log(
                'logout',
                "{$user->name} has successfully logged out.",
                $user->id
            );
        }
    }

    public function log(string $action, ?string $description = null, ?int $userId = null, array $meta = []): void
    {
        DB::table('user_activities')->insert([
            'user_id'    => $userId ?? auth()->id(),
            'event'      => ucfirst($action) . ' at: ' . now()->format('F d, Y h:i A'),
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getLogs(?int $userId = null, ?string $action = null): array
    {
        $query = DB::table('user_activities');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($action !== null) {
            $query->where('event', 'like', ucfirst($action) . '%');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
    /**
     * Hash the given value
     */
    public function hash(string $value): string
    {
        return $this->hasher->make($value);
    }

    /**
     * Check if the given hash matches the value
     */
    public function checkHash(string $value, string $hash): bool
    {
        return $this->hasher->check($value, $hash);
    }
}
