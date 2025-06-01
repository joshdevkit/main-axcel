<?php

namespace Axcel\Contracts\Auth;

use Axcel\Database\Model;

class AuditTrailingContract extends Model
{
    /**
     * Get the audit activity logs for the user.
     *
     * @return \Axcel\Database\Relations\HasMany
     */
    public function activities()
    {
        // Anonymous model for user_activities table
        $activityModel = new class extends Model {
            protected  $table = 'user_activities';
        };

        return new \Axcel\Database\Relations\HasMany(
            $activityModel,
            $this,
            'user_id',
            $this->getKeyName()
        );
    }
}
