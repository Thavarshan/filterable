<?php

namespace Filterable\Tests;

use BadMethodCallException;
use Filterable\Attributes\UseFilter;
use Filterable\Filter;
use Filterable\Providers\FilterableServiceProvider;
use Filterable\Tests\Fixtures\MockFilter;
use Filterable\Traits\AttributeFilterable;
use Filterable\Traits\Filterable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UseFilterTest extends TestCase
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  array<string, mixed>  $options
     * @return Builder<TModel>
     */
    private function filterQuery(string $model, ?\Filterable\Contracts\Filter $filter = null, array $options = []): Builder
    {
        $result = $model::__callStatic('filter', [$filter, $options]);
        $this->assertInstanceOf(Builder::class, $result);

        return $result;
    }

    public function test_attribute_resolves_current_request_without_executing_queries(): void
    {
        app()->instance('request', Request::create('/', 'GET', ['name' => 'Alice']));
        $connection = (new AttributedPost)->getConnection();
        $connection->enableQueryLog();
        $builder = AttributedPost::query()->where('is_visible', true);
        $this->assertSame($builder, $builder->__call('filter', []));
        $this->assertSame([true, '%Alice%'], $builder->getBindings());
        $this->assertSame([], $connection->getQueryLog());
        $connection->disableQueryLog();
    }

    public function test_static_calls_resolve_a_fresh_filter_and_current_request(): void
    {
        app()->instance('request', Request::create('/', 'GET', ['name' => 'Alice']));
        $this->assertSame(['%Alice%'], $this->filterQuery(AttributedPost::class)->getBindings());
        app()->instance('request', Request::create('/', 'GET', ['name' => 'Bob']));
        $this->assertSame(['%Bob%'], $this->filterQuery(AttributedPost::class)->getBindings());
    }

    public function test_explicit_filter_and_options_are_supported(): void
    {
        $filter = new MockFilter(new Request(['email' => 'example.com']));
        $builder = $this->filterQuery(AttributedPost::class, $filter, ['cache_context' => 'tenant']);
        $this->assertSame(['%example.com%'], $builder->getBindings());
        $this->assertSame(['cache_context' => 'tenant'], $filter->getOptions());
        $this->assertSame($builder, $filter->getBuilder());
    }

    public function test_attribute_is_inherited_and_child_declaration_overrides_it(): void
    {
        app()->instance('request', new Request(['name' => 'Alice']));
        $this->assertSame(['%Alice%'], $this->filterQuery(InheritedPost::class)->getBindings());
        $this->assertSame(['override', 'other'], $this->filterQuery(OverridePost::class)->getBindings());
    }

    public function test_existing_trait_and_custom_scopes_take_precedence(): void
    {
        $filter = new MockFilter(new Request(['name' => 'Alice']));
        $this->assertSame(['%Alice%'], LegacyPost::filter($filter)->getBindings());
        $this->assertSame(['custom'], CustomScopePost::filter()->getBindings());
    }

    public function test_provider_preserves_an_existing_global_macro(): void
    {
        $original = Builder::getGlobalMacro('filter');
        $custom = fn () => 'external';
        try {
            Builder::macro('filter', $custom);
            (new FilterableServiceProvider(app()))->packageBooted();
            $this->assertSame($custom, Builder::getGlobalMacro('filter'));
            $this->assertSame('external', AttributedPost::query()->__call('filter', []));
        } finally {
            Builder::macro('filter', $original);
        }
    }

    public function test_named_options_reach_the_resolved_filter(): void
    {
        $filter = new MockFilter(new Request(['name' => 'Alice']));
        app()->bind(MockFilter::class, fn () => $filter);
        $query = AttributedPost::query();
        $this->assertSame($query, $query->__call('filter', ['options' => ['cache_context' => 'tenant']]));
        $this->assertSame(['cache_context' => 'tenant'], $filter->getOptions());
    }

    public function test_custom_scope_arguments_and_return_value_are_preserved(): void
    {
        $this->assertSame('custom:value', ScalarScopePost::query()->__call('filter', ['value']));
    }

    public function test_native_scope_trait_still_works(): void
    {
        if (! class_exists(Scope::class)) {
            $this->markTestSkipped('Native scope attributes require Laravel 12+.');
        }
        $filter = new MockFilter(new Request(['name' => 'Alice']));
        $result = NativeScopePost::query()->__call('filter', [$filter]);
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertSame(['%Alice%'], $result->getBindings());
    }

    public function test_attribute_queries_return_records_and_paginate(): void
    {
        $model = new AttributedPost;
        $model->getConnection()->table('mocks')->insert([
            ['name' => 'Alice', 'is_visible' => true],
            ['name' => 'Bob', 'is_visible' => true],
        ]);
        app()->instance('request', new Request(['name' => 'Alice']));
        $query = $this->filterQuery(AttributedPost::class);
        $this->assertInstanceOf(AttributedPost::class, $query->firstOrFail());
        $this->assertSame(['Alice'], $query->pluck('name')->all());
        $this->assertSame(1, $this->filterQuery(AttributedPost::class)->paginate(10)->total());
    }

    public function test_unannotated_models_fail_clearly(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('must declare #[UseFilter]');
        $this->filterQuery(UnannotatedPost::class);
    }

    public function test_invalid_filter_class_fails_before_container_resolution(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->filterQuery(InvalidPost::class);
    }

    public function test_filter_conditions_preserve_scope_grouping(): void
    {
        $query = OverridePost::query()->where('is_visible', true);
        $query->__call('filter', []);
        $this->assertStringContainsString('and (', $query->toSql());
        $this->assertSame([true, 'override', 'other'], $query->getBindings());
    }
}

#[UseFilter(MockFilter::class)]
class AttributedPost extends Model
{
    protected $table = 'mocks';
}

class InheritedPost extends AttributedPost {}

#[UseFilter(OverrideFilter::class)]
class OverridePost extends AttributedPost {}

class OverrideFilter extends Filter
{
    /** @param Builder<*> $builder */
    public function apply(Builder $builder, ?array $options = []): Builder
    {
        return $builder->where('name', 'override')->orWhere('name', 'other');
    }
}

class LegacyPost extends AttributedPost
{
    use Filterable;
}

class CustomScopePost extends AttributedPost
{
    /** @param Builder<*> $query */
    public function scopeFilter(Builder $query): void
    {
        $query->where('name', 'custom');
    }
}

class UnannotatedPost extends Model {}

#[UseFilter('stdClass')]
class InvalidPost extends Model {}

class NativeScopePost extends AttributedPost
{
    use AttributeFilterable;
}

class ScalarScopePost extends AttributedPost
{
    /** @param Builder<*> $query */
    public function scopeFilter(Builder $query, string $value): string
    {
        return 'custom:'.$value;
    }
}
