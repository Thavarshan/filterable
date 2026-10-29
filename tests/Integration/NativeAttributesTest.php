<?php

namespace Filterable\Tests\Integration;

use Filterable\Attributes\AsFilter;
use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Filterable\Traits\AttributeFilterable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class NativeAttributesTest extends TestCase
{
    public function test_optional_native_scope_matches_legacy_scope(): void
    {
        if (! class_exists(Scope::class)) {
            $this->markTestSkipped('Attributed Eloquent scopes require Laravel 12+.');
        }
        MockFilterable::factory()->create(['name' => 'ALICE', 'status' => 'active']);
        $model = new class extends Model
        {
            use AttributeFilterable;

            protected $table = 'mocks';
        };
        $a = $model->newQuery()->filter(new AttributedLifecycleFilter(new Request(['status' => 'active'])));
        $b = MockFilterable::query()->filter(new AttributedLifecycleFilter(new Request(['status' => 'active'])));
        $this->assertSame($a->toSql(), $b->toSql());
        $this->assertSame($a->getBindings(), $b->getBindings());
        $this->assertSame($a->pluck('id')->all(), $b->pluck('id')->all());
    }

    public function test_php85_callables_and_discard_diagnostics(): void
    {
        if (PHP_VERSION_ID < 80500) {
            $this->markTestSkipped('PHP 8.5 syntax is loaded only on PHP 8.5+.');
        }
        require_once __DIR__.'/../Fixtures/php85/callables.php85';
        MockFilterable::factory()->create(['name' => 'ALICE']);
        foreach (['Filterable\\Tests\\Fixtures\\Php85\\CallableFilter', 'Filterable\\Tests\\Fixtures\\Php85\\FirstClassCallableFilter'] as $class) {
            $filter = new $class(new Request(['name' => 'alice']));
            $this->assertRuntimeInstanceOf(Filter::class, $filter);
            $this->assertSame(['ALICE'], $filter->apply(MockFilterable::query())->getBindings());
            $this->assertCount(1, $filter->get());
        }
        $warnings = [];
        set_error_handler(function ($severity, $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        });
        try {
            $discard = 'Filterable\\Tests\\Fixtures\\Php85\\discardLazy';

            $discard($filter);
            $this->assertCount(1, $warnings);
            $this->assertStringContainsString('return value', strtolower($warnings[0]));
            $intentional = 'Filterable\\Tests\\Fixtures\\Php85\\intentionallyDiscardLazy';

            $intentional($filter);
            $this->assertCount(1, $warnings);
        } finally {
            restore_error_handler();
        }
    }

    public function test_normal_http_request_rebinding_and_transient_filters(): void
    {
        app('router')->get('/filterable/current', CurrentRequestController::class);
        $this->getJson('/filterable/current?name=alice')->assertOk()->assertExactJson(['name' => 'alice']);
        $this->getJson('/filterable/current?name=bob')->assertOk()->assertExactJson(['name' => 'bob']);
        $first = app(CurrentRequestFilter::class);
        $second = app(CurrentRequestFilter::class);
        $this->assertNotSame($first, $second);
        $custom = new Request(['name' => 'custom']);
        app()->instance(Request::class, $custom);
        $this->assertSame(['name' => 'custom'], app(CurrentRequestFilter::class)->getFilterables());
        $this->assertSame(['name' => 'manual'], (new CurrentRequestFilter(new Request(['name' => 'manual'])))->getFilterables());
    }
}

class CurrentRequestController
{
    /** @return array<string, mixed> */
    public function __invoke(CurrentRequestFilter $filter): array
    {
        return $filter->getFilterables();
    }
}

class CurrentRequestFilter extends Filter
{
    #[AsFilter('name')]
    protected function name(string $value): void
    {
        $this->resolveBuilder()->where('name', $value);
    }
}
