<?php

namespace Filterable\Tests\Integration;

use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;

class NativeAttributesTest extends TestCase
{
    public function test_optional_native_scope_matches_legacy_scope(): void
    {
        if (! class_exists(\Illuminate\Database\Eloquent\Attributes\Scope::class)) {
            $this->markTestSkipped('Attributed Eloquent scopes require Laravel 12+.');
        }
        MockFilterable::factory()->create(['name' => 'ALICE', 'status' => 'active']);
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            use \Filterable\Traits\AttributeFilterable;
            protected $table = 'mocks';
        };
        $a = $model->newQuery()->filter(new AttributedLifecycleFilter(new Request(['status' => 'active'])));
        $b = MockFilterable::query()->filter(new AttributedLifecycleFilter(new Request(['status' => 'active'])));
        $this->assertSame($a->toSql(), $b->toSql());
        $this->assertSame($a->getBindings(), $b->getBindings());
        $this->assertSame($a->get()->modelKeys(), $b->get()->modelKeys());
    }

    public function test_php85_callables_and_discard_diagnostics(): void
    {
        if (PHP_VERSION_ID < 80500) {
            $this->markTestSkipped('PHP 8.5 syntax is loaded only on PHP 8.5+.');
        }
        require_once __DIR__.'/../Fixtures/php85/callables.php85';
        MockFilterable::factory()->create(['name' => 'ALICE']);
        foreach ([\Filterable\Tests\Fixtures\Php85\CallableFilter::class, \Filterable\Tests\Fixtures\Php85\FirstClassCallableFilter::class] as $class) {
            $filter = new $class(new Request(['name' => 'alice']));
            $this->assertSame(['ALICE'], $filter->apply(MockFilterable::query())->getBindings());
            $this->assertCount(1, $filter->get());
        }
        $warnings = [];
        set_error_handler(function ($severity, $message) use (&$warnings) { $warnings[] = $message; return true; });
        try {
            \Filterable\Tests\Fixtures\Php85\discardLazy($filter);
            $this->assertCount(1, $warnings);
            $this->assertStringContainsString('return value', strtolower($warnings[0]));
            \Filterable\Tests\Fixtures\Php85\intentionallyDiscardLazy($filter);
            $this->assertCount(1, $warnings);
        } finally {
            restore_error_handler();
        }
    }

    public function test_normal_http_request_rebinding_and_transient_filters(): void
    {
        $this->app['router']->get('/filterable/current', CurrentRequestController::class);
        $this->getJson('/filterable/current?name=alice')->assertOk()->assertExactJson(['name' => 'alice']);
        $this->getJson('/filterable/current?name=bob')->assertOk()->assertExactJson(['name' => 'bob']);
        $first = $this->app->make(CurrentRequestFilter::class);
        $second = $this->app->make(CurrentRequestFilter::class);
        $this->assertNotSame($first, $second);
        $custom = new Request(['name' => 'custom']);
        $this->app->instance(Request::class, $custom);
        $this->assertSame(['name' => 'custom'], $this->app->make(CurrentRequestFilter::class)->getFilterables());
        $this->assertSame(['name' => 'manual'], (new CurrentRequestFilter(new Request(['name' => 'manual'])))->getFilterables());
    }
}

class CurrentRequestController
{
    public function __invoke(CurrentRequestFilter $filter): array { return $filter->getFilterables(); }
}

class CurrentRequestFilter extends Filter
{
    #[\Filterable\Attributes\AsFilter('name')]
    protected function name(string $value): void { $this->getBuilder()->where('name', $value); }
}
