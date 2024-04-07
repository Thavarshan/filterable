<?php

namespace Filterable;

use Closure;
use Filterable\Interfaces\Handler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RequestHandler implements Handler
{
    /**
     * Create a new request handler instance.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function __construct(protected readonly Request $request)
    {
    }

    /**
     * Handle the request.
     *
     * @param Closure    $callback
     * @param array|null $options
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function handle(Closure $callback, ?array $options = []): Builder
    {
        return $callback($this->request, $options);
    }
}
