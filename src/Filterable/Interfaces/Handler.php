<?php

namespace Filterable\Interfaces;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Interface Filterable
 *
 * @package Filterable\Interfaces
 */
interface Handler
{
    /**
     * Handle the request.
     *
     * @param Closure    $callback
     * @param array|null $options
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function handle(Closure $callback, ?array $options = []): Builder;
}
