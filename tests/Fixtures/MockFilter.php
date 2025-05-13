<?php

namespace Filterable\Tests\Fixtures;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class MockFilter extends TestFilter
{
    /**
     * Registered filters.
     *
     * @var array<string>
     */
    protected array $filters = ['name', 'email'];

    /**
     * Create a new filter instance.
     */
    public function __construct(
        protected Request $request,
        ?Cache $cache = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($request, $cache, $logger);
    }

    /**
     * Filter by name.
     */
    protected function name(string $value): void
    {
        $this->builder->where('name', 'LIKE', "%{$value}%");
    }

    /**
     * Filter by email.
     */
    protected function email(string $value): void
    {
        $this->builder->where('email', 'LIKE', "%{$value}%");
    }
}
