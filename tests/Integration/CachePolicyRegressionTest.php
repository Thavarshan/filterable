<?php

namespace Filterable\Tests\Integration;

use Filterable\Attributes\CachePolicy;
use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class CachePolicyRegressionTest extends TestCase
{
    /** @return array<string, array{class-string<CacheRegressionFilter>, bool}> */
    public static function disabledResultPolicies(): array
    {
        return [
            'attribute disable' => [CountOnlyRegressionFilter::class, false],
            'runtime override of attribute enable' => [EnabledRegressionFilter::class, true],
        ];
    }

    /** @param class-string<CacheRegressionFilter> $class */
    #[DataProvider('disabledResultPolicies')]
    public function test_disabled_results_never_call_the_cache_repository(string $class, bool $runtimeDisable): void
    {
        $cache = \Mockery::mock(Repository::class);
        $cache->shouldNotReceive('remember', 'get', 'put', 'has', 'tags', 'supportsTags');
        $filter = new $class(new Request, $cache);
        if ($runtimeDisable) {
            $filter->cacheResults(false);
        }
        $filter->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));
        $this->assertCount(1, $filter->get());
        MockFilterable::factory()->create(['name' => 'BOB', 'status' => 'active', 'is_visible' => true]);
        $this->assertCount(2, $filter->get());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('array')->flush();
        MockFilterable::factory()->create(['name' => 'ALICE', 'status' => 'active', 'is_visible' => true]);
    }

    public function test_attribute_count_only_policy_never_reads_or_writes_result_cache(): void
    {
        $filter = new CountOnlyRegressionFilter(new Request, Cache::store('array'));
        $filter->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(['ALICE'], $filter->get()->pluck('name')->all());
        $this->assertSame(1, $filter->count());
        $this->assertCount(2, DB::getQueryLog());
        MockFilterable::factory()->create(['name' => 'BOB', 'status' => 'active', 'is_visible' => true]);
        DB::flushQueryLog();
        $this->assertSame(['ALICE', 'BOB'], $filter->get()->pluck('name')->all());
        $this->assertSame(1, $filter->count(), 'Count remains cached independently');
        $this->assertCount(1, DB::getQueryLog());
        $fresh = new CountOnlyRegressionFilter(new Request, Cache::store('array'));
        $fresh->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));
        $this->assertSame(['ALICE', 'BOB'], $fresh->get()->pluck('name')->all());
    }

    public function test_explicit_runtime_disable_ignores_an_existing_result_cache(): void
    {
        $make = function (): CacheRegressionFilter {
            $filter = new CacheRegressionFilter(new Request, Cache::store('array'));
            $filter->enableFeature('caching')->cacheCount();
            $filter->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));

            return $filter;
        };
        $warm = $make()->cacheResults();
        $this->assertCount(1, $warm->get());
        $this->assertSame(1, $warm->count());
        MockFilterable::factory()->create(['name' => 'BOB', 'status' => 'active', 'is_visible' => true]);
        $disabled = $make()->cacheResults(false);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertCount(2, $disabled->get());
        $this->assertSame(1, $disabled->count());
        $this->assertCount(1, DB::getQueryLog());
        $this->assertCount(1, $make()->cacheResults()->get(), 'Explicit disable did not overwrite the result entry');
    }

    public function test_attribute_runtime_overrides_automatic_mode_ttl_and_feature_disable(): void
    {
        $filter = new CountOnlyRegressionFilter(new Request, Cache::store('array'));
        $filter->cacheResults();
        $filter->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));
        $filter->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $filter->get();
        $this->assertSame([], DB::getQueryLog());
        $filter->cacheResults(false);
        $filter->get();
        $this->assertCount(1, DB::getQueryLog());
        $filter->setCacheExpiration(0);
        $filter->cacheResults();
        DB::flushQueryLog();
        $filter->get();
        $filter->count();
        $this->assertCount(2, DB::getQueryLog());
        $filter->setCacheExpiration(5);
        $filter->disableFeature('caching');
        DB::flushQueryLog();
        $filter->get();
        $filter->count();
        $this->assertCount(2, DB::getQueryLog());

        $automatic = new CacheRegressionFilter(new Request, Cache::store('array'));
        $automatic->enableFeature('caching');
        $automatic->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));
        $automatic->get();
        DB::flushQueryLog();
        $automatic->get();
        $this->assertSame([], DB::getQueryLog(), 'No explicit result policy retains automatic caching');
    }

    public function test_eloquent_callbacks_bypass_warm_results_and_run_for_every_get(): void
    {
        $warm = new CacheRegressionFilter(new Request, Cache::store('array'));
        $warm->enableFeature('caching')->cacheResults();
        $warm->apply(MockFilterable::query());
        $warm->get();
        $calls = [];
        foreach (['A', 'B'] as $marker) {
            $query = MockFilterable::query()->afterQuery(function ($rows) use ($marker, &$calls) {
                $this->assertInstanceOf(Collection::class, $rows);
                $calls[] = $marker;
                foreach ($rows as $row) {
                    $row->setAttribute('callback_marker', $marker);
                }

                return $rows;
            });
            $filter = new CacheRegressionFilter(new Request, Cache::store('array'));
            $filter->enableFeature('caching')->cacheResults();
            $filter->apply($query);
            for ($i = 0; $i < 2; $i++) {
                $this->assertSame([$marker], $filter->get()->pluck('callback_marker')->all());
            }
        }
        $this->assertSame(['A', 'A', 'B', 'B'], $calls);
        $this->assertSame([null], $warm->get()->pluck('callback_marker')->all(), 'Callback output never overwrites callback-free cache');
    }

    public function test_base_query_callbacks_also_bypass_result_cache(): void
    {
        $calls = 0;
        $query = MockFilterable::query();
        $query->getQuery()->afterQuery(function ($rows) use (&$calls) {
            $calls++;

            return $rows;
        });
        $filter = new CacheRegressionFilter(new Request, Cache::store('array'));
        $filter->enableFeature('caching')->cacheResults();
        $filter->apply($query);
        $filter->get();
        $filter->get();
        $this->assertSame(2, $calls);
    }

    public function test_automatic_policy_can_be_restored_and_declared_as_an_attribute(): void
    {
        $filter = new AutomaticRegressionFilter(new Request, Cache::store('array'));
        $filter->apply(MockFilterable::query()->where('status', 'active')->where('is_visible', true));
        $filter->cacheResults(false)->autoCacheResults();
        $filter->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $filter->get();
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_eager_loading_callbacks_and_callback_free_cache_remain_independent(): void
    {
        $model = new class extends MockFilterable
        {
            /** @return HasMany<MockFilterable, $this> */
            public function peers(): HasMany
            {
                return $this->hasMany(MockFilterable::class, 'status', 'status');
            }
        };
        $calls = 0;
        $filter = new CacheRegressionFilter(new Request, Cache::store('array'));
        $filter->enableFeature('caching')->cacheResults();
        $filter->apply($model->newQuery()->with(['peers' => function ($query) use (&$calls) {
            $calls++;
        }]));
        $filter->get();
        $filter->get();
        $this->assertSame(2, $calls);
    }

    public function test_callbacks_registered_by_global_scopes_run_on_each_get(): void
    {
        $counter = new \stdClass;
        $counter->calls = 0;
        $model = new MockFilterable;
        $query = $model->newQuery()->withGlobalScope('callback', function ($builder) use ($counter) {
            $this->assertInstanceOf(Builder::class, $builder);
            $builder->getQuery()->afterQuery(function ($rows) use ($counter) {
                $counter->calls++;

                return $rows;
            });
        });
        $filter = new CacheRegressionFilter(new Request, Cache::store('array'));
        $filter->enableFeature('caching')->cacheResults();
        $filter->apply($query);
        $this->assertSame(0, $counter->calls);
        $filter->get();
        $filter->get();
        $this->assertSame(2, $counter->calls);
    }
}

class CacheRegressionFilter extends Filter
{
    public function cacheKey(): string
    {
        return $this->buildCacheKey();
    }
}

#[CachePolicy(results: false, count: true)]
class CountOnlyRegressionFilter extends CacheRegressionFilter {}

#[CachePolicy(results: null)]
class AutomaticRegressionFilter extends CacheRegressionFilter {}

#[CachePolicy(results: true)]
class EnabledRegressionFilter extends CacheRegressionFilter {}
