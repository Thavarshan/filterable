<?php

namespace Filterable\Tests\Integration;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Cache\RedisStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ServiceIntegrationTest extends TestCase
{
    public function test_real_database_and_redis_hits_misses_and_tags(): void
    {
        if (! getenv('FILTERABLE_TEST_DB') || ! getenv('FILTERABLE_TEST_REDIS')) {
            $this->markTestSkipped('Requires the service CI job or documented local services.');
        }
        $this->assertSame(getenv('FILTERABLE_TEST_DB'), DB::connection()->getDriverName());
        config()->set('database.redis.client', 'phpredis');
        config()->set('cache.stores.filterable_redis', ['driver' => 'redis', 'connection' => 'default']);
        $cache = Cache::store('filterable_redis');
        $this->assertRuntimeInstanceOf(RedisStore::class, $cache->getStore());
        MockFilterable::factory()->create(['name' => 'ALICE', 'status' => 'active']);
        MockFilterable::factory()->create(['name' => 'BOB', 'status' => 'inactive']);
        $tag = 'filterable-service-'.bin2hex(random_bytes(8));
        $make = fn ($status) => (new AttributedLifecycleFilter(new Request(['status' => $status]), $cache))
            ->enableFeature('caching')->cacheResults()->cacheCount()->cacheTags($tag);
        try {
            $filter = $make('active');
            DB::enableQueryLog();
            DB::flushQueryLog();
            $filter->apply(MockFilterable::query());
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame(['ALICE'], $filter->get()->pluck('name')->all());
            $this->assertSame(1, $filter->count());
            $this->assertCount(2, DB::getQueryLog());
            $hit = $make('active');
            $hit->apply(MockFilterable::query());
            DB::flushQueryLog();
            $this->assertSame(['ALICE'], $hit->get()->pluck('name')->all());
            $this->assertSame(1, $hit->count());
            $this->assertSame([], DB::getQueryLog());
            $different = $make('inactive');
            $different->apply(MockFilterable::query());
            $this->assertSame(['BOB'], $different->get()->pluck('name')->all());
            $filter->clearCache();
            DB::flushQueryLog();
            $filter->get();
            $filter->count();
            $this->assertCount(2, DB::getQueryLog());
        } finally {
            $cache->tags([$tag])->flush();
        }
    }
}
