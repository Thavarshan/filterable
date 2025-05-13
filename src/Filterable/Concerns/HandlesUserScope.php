<?php

namespace Filterable\Concerns;

use Filterable\Contracts\Filter;
use Illuminate\Contracts\Auth\Authenticatable;

trait HandlesUserScope
{
    /**
     * The authenticated user to filter by.
     */
    protected ?Authenticatable $forUser = null;

    /**
     * Filter the query to only include records for the authenticated user.
     */
    public function forUser(?Authenticatable $user): Filter
    {
        $this->forUser = $user;

        return $this;
    }

    /**
     * Apply the user filter to the query.
     */
    protected function applyUserScope(): void
    {
        if (is_null($this->forUser)) {
            return;
        }

        $attribute = $this->forUser->getAuthIdentifierName();
        $value = $this->forUser->getAuthIdentifier();

        if (method_exists($this, 'logInfo')) {
            $this->logInfo('Applying user-specific filter', [
                'attribute' => $attribute,
                'value' => $value,
            ]);
        }

        $this->builder->where($attribute, $value);
    }
}
