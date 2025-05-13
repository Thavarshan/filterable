<?php

namespace Filterable\Concerns;

use Closure;
use Filterable\Contracts\Filter;

trait HandlesPreFilters
{
    /**
     * The pre-filters to apply to the query.
     */
    protected ?Closure $preFilters = null;

    /**
     * Register pre-filters to apply to the query.
     */
    public function registerPreFilters(Closure $callback): Filter
    {
        $this->preFilters = $callback;

        return $this;
    }

    /**
     * Apply pre-filters to the query.
     */
    protected function applyPreFilters(): void
    {
        if ($this->preFilters === null) {
            return;
        }

        // Only log if the logging feature is enabled
        $this->logInfo('Applying pre-filters');

        // Apply pre-filters to builder
        ($this->preFilters)($this->builder);
    }
}
