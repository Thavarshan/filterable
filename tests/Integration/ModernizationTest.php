<?php

namespace Filterable\Tests\Integration;

use Carbon\CarbonImmutable;
use Filterable\Attributes\AsFilter;
use Filterable\Attributes\CachePolicy;
use Filterable\Attributes\FilterComplexity;
use Filterable\Attributes\FilterFeatures;
use Filterable\Attributes\FilterLimits;
use Filterable\Attributes\FilterRules;
use Filterable\Attributes\RequiresPermission;
use Filterable\Attributes\TransformWith;
use Filterable\Definitions\DefinitionRegistry;
use Filterable\Filter;
use Filterable\QueryFilter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ModernizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MockFilterable::factory()->create(['name' => 'ALICE', 'age' => 0, 'status' => 'active', 'is_visible' => false]);
        MockFilterable::factory()->create(['name' => 'BOB', 'age' => 30, 'status' => 'inactive', 'is_visible' => true]);
        Cache::store('array')->flush();
    }

    public function test_request_transformations_survive_every_getter_and_cache_hit(): void
    {
        foreach ([false, true] as $caching) {
            for ($i = 0; $i < 2; $i++) {
                $filter = new AttributedLifecycleFilter(Request::create('/?name=alice&age=0&helper=attack'), Cache::store('array'));
                $filter->cacheResults()->cacheCount()->cacheTags(['filters']);
                if ($caching) {
                    $filter->enableFeature('caching');
                }
                DB::enableQueryLog();
                DB::flushQueryLog();
                $query = $filter->apply(MockFilterable::query());
                $this->assertSame([], DB::getQueryLog(), 'apply must never execute a query');
                $this->assertSame(['ALICE', '0'], $query->getBindings());
                $this->assertSame(['name' => 'ALICE', 'age' => '0'], $filter->getFilterables());
                $this->assertSame(['ALICE'], $query->get()->pluck('name')->all());
                $this->assertSame(['ALICE'], $filter->get()->pluck('name')->all());
                $this->assertSame(1, $filter->count());
                DB::flushQueryLog();
                $filter->get();
                $filter->count();
                $this->assertCount($caching ? 0 : 2, DB::getQueryLog());
            }
        }
    }

    public function test_zero_false_empty_semantics_precedence_and_reset(): void
    {
        $request = new Request(['age' => 0, 'visible' => false, 'name' => null, 'status' => '', 'ignored' => []]);
        $filter = new AttributedLifecycleFilter($request);
        $query = $filter->apply(MockFilterable::query());
        $this->assertSame([0, false], $query->getBindings());
        $this->assertSame(['ALICE'], $query->get()->pluck('name')->all());
        $request->replace(['name' => 'bob']);
        $this->assertSame(['age' => 0, 'visible' => false], $filter->getFilterables());
        $filter->reset()->appendFilterable('name', 'alice');
        $this->assertSame(['ALICE'], $filter->apply(MockFilterable::query())->get()->pluck('name')->all());
        $filter->reset();
        $this->assertSame(['BOB'], $filter->apply(MockFilterable::query())->get()->pluck('name')->all());
    }

    public function test_invalid_request_never_mutates_query(): void
    {
        $filter = new AttributedLifecycleFilter(Request::create('/?name=alice&age=invalid'));
        $query = MockFilterable::query();
        try {
            $filter->apply($query);
            $this->fail('Expected validation exception');
        } catch (ValidationException) {
            $this->assertSame([], $query->getBindings());
        }
    }

    public function test_disabled_concerns_and_runtime_overrides(): void
    {
        $filter = new AttributedLifecycleFilter(Request::create('/?name=alice&age=invalid'));
        $filter->disableFeature('validation')->disableFeature('valueTransformation');
        $query = $filter->apply(MockFilterable::query());
        $this->assertSame(['alice', 'invalid'], $query->getBindings());
    }

    public function test_gate_denials_guests_and_actor_separate_from_row_scope(): void
    {
        Gate::define('sensitive', fn ($user) => $user->getAuthIdentifier() === 100);
        foreach ([null, new GenericUser(['id' => 200]), new GenericUser(['id' => 100])] as $actor) {
            $filter = new PermissionFilter(Request::create('/?status=active'));
            $filter->authorizeFor($actor)->enableFeature('caching')->cacheResults();
            $query = $filter->apply(MockFilterable::query());
            $allowed = $actor?->getAuthIdentifier() === 100;
            $this->assertSame($allowed ? ['active'] : [], $query->getBindings());
            $this->assertSame($allowed ? ['status'] : [], $filter->getDebugInfo()['filters_applied']);
            $this->assertCount($allowed ? 1 : 2, $filter->get());
            $this->assertSame($allowed ? ['status' => 'active'] : [], $filter->getFilterables());
        }
    }

    public function test_permission_rejection_cannot_be_swallowed(): void
    {
        $filter = new class(Request::create('/?status=active')) extends Filter {
            #[AsFilter('status'), RequiresPermission('sensitive', reject: true)]
            protected function status(string $value): void { $this->builder->where('status', $value); }
        };
        $query = MockFilterable::query();
        try {
            $filter->apply($query);
            $this->fail('Expected authorization exception');
        } catch (AuthorizationException) {
            $this->assertSame([], $query->getBindings());
        }
    }

    public function test_any_all_permissions_and_complexity_after_transformation(): void
    {
        Gate::define('allow', fn () => true);
        Gate::define('deny', fn () => false);
        $actor = new GenericUser(['id' => 100]);
        $filter = new class(new Request(['any' => 'active', 'all' => 'inactive'])) extends Filter {
            #[AsFilter('any'), RequiresPermission(['allow', 'deny'], any: true)]
            protected function any(string $value): void { $this->builder->where('status', $value); }
            #[AsFilter('all'), RequiresPermission(['allow', 'deny'])]
            protected function all(string $value): void { $this->builder->where('status', $value); }
        };
        $this->assertSame(['active'], $filter->authorizeFor($actor)->apply(MockFilterable::query())->getBindings());

        $filter = new class(new Request(['names' => 'ALICE,BOB'])) extends Filter {
            #[AsFilter('names'), TransformWith([self::class, 'split']), FilterComplexity(3)]
            protected function names(array $value): void { $this->builder->whereIn('name', $value); }
            public static function split(string $value): array { return explode(',', $value); }
        };
        $filter->setMaxComplexity(5);
        $this->expectException(AuthorizationException::class);
        $filter->apply(MockFilterable::query());
    }

    public function test_class_precedence_and_no_metadata_io(): void
    {
        config()->set('filterable.defaults.features.caching', true);
        config()->set('filterable.defaults.cache.ttl', 100);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $filter = new ChildDefaultsFilter(new Request);
        $this->assertSame([], DB::getQueryLog());
        $this->assertFalse($filter->hasFeature('caching'));
        $this->assertSame(2, $filter->getCacheExpiration());
        $filter->setCacheExpiration(0)->enableFeature('caching');
        $this->assertTrue($filter->hasFeature('caching'));
        $this->assertSame(0, $filter->getCacheExpiration());
    }

    public function test_cache_identity_and_unsupported_tag_store_invalidation(): void
    {
        $make = fn ($status) => (new AttributedLifecycleFilter(new Request(['status' => $status]), Cache::store('array')))
            ->enableFeature('caching')->cacheResults()->cacheCount()->cacheTags('filters');
        $active = $make('active');
        $active->apply(MockFilterable::query());
        $this->assertSame(['ALICE'], $active->get()->pluck('name')->all());
        $inactive = $make('inactive');
        $inactive->apply(MockFilterable::query());
        $this->assertSame(['BOB'], $inactive->get()->pluck('name')->all());
        Cache::store('array')->put('unrelated', 1);
        $active->clearRelatedCaches(MockFilterable::class);
        $this->assertSame(1, Cache::store('array')->get('unrelated'));
        $active->count();
        $active->clearCache();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $active->get();
        $active->count();
        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_disabled_cache_never_touches_store_or_queries_during_apply(): void
    {
        $store = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $store->shouldNotReceive('remember', 'get', 'put');
        $filter = new AttributedLifecycleFilter(new Request(['name' => 'alice']), $store);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $filter->apply(MockFilterable::query());
        $this->assertSame([], DB::getQueryLog());
        $this->assertCount(1, $filter->get());
    }

    public function test_registry_inheritance_traits_overrides_and_legacy_equivalence(): void
    {
        DefinitionRegistry::clear();
        $first = DefinitionRegistry::forClass(AttributedLifecycleFilter::class);
        $this->assertSame($first, DefinitionRegistry::forClass(AttributedLifecycleFilter::class));
        $request = new Request(['status' => 'active']);
        $attributed = new AttributedLifecycleFilter($request);
        $legacy = new class($request) extends Filter {
            protected array $filters = ['status'];
            protected function status(string $value): void { $this->builder->where('status', $value); }
        };
        $a = $attributed->apply(MockFilterable::query());
        $b = $legacy->apply(MockFilterable::query());
        $this->assertSame($a->toSql(), $b->toSql());
        $this->assertSame($a->getBindings(), $b->getBindings());
        $this->assertSame($a->get()->modelKeys(), $b->get()->modelKeys());
        $child = new class(new Request(['name' => 'alice'])) extends AttributedLifecycleFilter {
            protected function name(string $value): void { $this->builder->where('name', 'BOB'); }
        };
        $this->assertSame([], $child->apply(MockFilterable::query())->getBindings(), 'Overriding without AsFilter unregisters the inherited method');
    }

    public function test_duplicate_alias_and_invalid_signature_fail_before_query_mutation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new class(new Request) extends Filter {
            #[AsFilter('same')]
            protected function one(string $value): void {}
            #[AsFilter('same')]
            protected function two(string $value): void {}
        };
    }

    public function test_legacy_conflicts_and_unregistered_helpers_are_rejected(): void
    {
        $filter = new AttributedLifecycleFilter(new Request);
        $filter->appendFilterable('helper', 'attack');
        $query = MockFilterable::query();
        try {
            $filter->apply($query);
            $this->fail('Expected definition exception');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $query->getBindings());
        }
        $this->expectException(InvalidArgumentException::class);
        new class(new Request) extends Filter {
            protected array $filterMethodMap = ['name' => 'other'];
            #[AsFilter('name')]
            protected function name(string $value): void {}
            protected function other(string $value): void {}
        };
    }

    public function test_real_cursor_and_query_callback_paths(): void
    {
        $filter = new AttributedLifecycleFilter(new Request(['status' => 'active']));
        $query = MockFilterable::query()->tap(new QueryFilter($filter));
        $this->assertSame(['active'], $query->getBindings());
        $this->assertInstanceOf(\Generator::class, $filter->cursor());
        $this->assertSame(['ALICE'], array_map(fn ($row) => $row->name, iterator_to_array($filter->cursor())));
    }
}

trait StatusHandler
{
    #[AsFilter('status')]
    protected function status(string $value): void { $this->builder->where('status', $value); }
}

class AttributedLifecycleFilter extends Filter
{
    use StatusHandler;

    #[AsFilter('name'), FilterRules(['string']), TransformWith('strtoupper')]
    protected function name(string $value): void { $this->builder->where('name', $value); }

    #[AsFilter('age'), FilterRules(['integer'])]
    protected function age(mixed $value): void { $this->builder->where('age', $value); }

    #[AsFilter('visible')]
    protected function visible(bool $value): void { $this->builder->where('is_visible', $value); }

    public function helper(string $value): void { throw new \LogicException('Helper must never be request-invocable'); }
}

class PermissionFilter extends Filter
{
    #[AsFilter('status'), RequiresPermission('sensitive')]
    protected function status(string $value): void { $this->builder->where('status', $value); }
}

#[CachePolicy(ttlMinutes: 8), FilterLimits(maxFilters: 4)]
class ParentDefaultsFilter extends Filter {}

#[CachePolicy(ttlMinutes: 2), FilterFeatures(['caching' => false])]
class ChildDefaultsFilter extends ParentDefaultsFilter {}
