<?php

namespace Filterable\Tests\Fixtures;

use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;

class MockFilter extends Filter
{
    /**
     * The filters that should be applied to the data.
     *
     * @var array
     */
    protected array $filters = [
        'name',
        'email',
    ];

    /**
     * Filter the query by a given name.
     *
     * @param string $name
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function name(string $name): Builder
    {
        return $this->builder->where('name', 'LIKE', "%{$name}%");
    }

    /**
     * Filter the query by a given email.
     *
     * @param string $email
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function email(string $email): Builder
    {
        return $this->builder->where('email', $email);
    }
}
