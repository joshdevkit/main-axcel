<?php

namespace Axcel\Facades;

use Axcel\Support\Facade;

/**
 * @method static bool attempt(array $credentials, bool $remember = false)
 * @method static void login(\Axcel\Database\Model $user, bool $remember = false)
 * @method static bool once(array $credentials)
 * @method static void logout()
 * @method static \Axcel\Database\Model|null user()
 * @method static mixed id()
 * @method static bool check()
 * @method static bool guest()
 * @method static \Axcel\Contracts\Auth\AuthManager setUser(\Axcel\Database\Model $user)
 * @method static bool validate(array $credentials)
 * @method static string hash(string $value)
 * @method static bool checkHash(string $value, string $hash)
 * @method static log(string $event, $integer $user_id)
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}
