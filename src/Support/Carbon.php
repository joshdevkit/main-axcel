<?php

namespace Axcel\Support;

use Carbon\Carbon as BaseCarbon;
use Carbon\CarbonImmutable as BaseCarbonImmutable;
use Symfony\Component\Uid\Ulid;
use Ramsey\Uuid\Uuid;

class Carbon extends BaseCarbon
{
    /**
     * Set the "test now" static date (for mocking).
     *
     * @param \Carbon\Carbon|string|\DateTimeInterface|null $testNow
     */
    public static function setTestNow(mixed $testNow = null): void
    {
        BaseCarbon::setTestNow($testNow);
        BaseCarbonImmutable::setTestNow($testNow);
    }

    /**
     * Create a Carbon instance from a UUID or ULID.
     *
     * @param \Ramsey\Uuid\Uuid|\Symfony\Component\Uid\Ulid|string $id
     * @return static
     */
    public static function createFromId(Uuid|Ulid|string $id): static
    {
        if (is_string($id)) {
            $id = Ulid::isValid($id) ? Ulid::fromString($id) : Uuid::fromString($id);
        }

        return static::createFromInterface($id->getDateTime());
    }
}
