<?php

namespace Axcel\Contracts\Auth;

interface AuditTrailing
{
    /**
     * Log an audit event.
     *
     * @param string $action
     * @param string|null $description
     * @param int|null $userId
     * @param array $meta Additional contextual data (optional)
     * @return void
     */
    public function log(string $action, ?string $description = null, ?int $userId = null, array $meta = []): void;

    /**
     * Retrieve audit logs.
     *
     * @param int|null $userId
     * @param string|null $action
     * @return array
     */
    public function getLogs(?int $userId = null, ?string $action = null): array;
}
