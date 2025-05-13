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
        if (is_null($this->preFilters)) {
            return;
        }

        if (method_exists($this, 'logInfo')) {
            $this->logInfo('Applying pre-filters');
        }

        call_user_func($this->preFilters, $this->builder);
    }
}
