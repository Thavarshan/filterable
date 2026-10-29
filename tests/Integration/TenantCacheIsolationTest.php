<?php

namespace Filterable\Tests\Integration;

use Filterable\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

class TenantCacheIsolationTest extends TestCase
{
    /** @return array<string, array{?string}> */
    public static function modelConnections(): array
    {
        return ['default connection' => [null], 'explicit connection' => ['tenant']];
    }

    #[DataProvider('modelConnections')]
    public function test_same_named_databases_on_distinct_endpoints_have_distinct_cache_entries(?string $modelConnection): void
    {
        // Two real SQLite databases with identical names, reached through a test
        // connector mapping endpoints to separate PDO instances. CI also verifies
        // the same scenario against two separate MySQL servers and shared Redis.
        $pdos = ['tenant-a' => new PDO('sqlite::memory:'), 'tenant-b' => new PDO('sqlite::memory:')];
        foreach ($pdos as $host => $pdo) {
            $pdo->exec('CREATE TABLE cache_tenant_records (id INTEGER PRIMARY KEY, marker VARCHAR(20))');
            $pdo->exec($host === 'tenant-a'
                ? "INSERT INTO cache_tenant_records VALUES (1, 'A'), (2, 'A')"
                : "INSERT INTO cache_tenant_records VALUES (1, 'B')");
        }
        DB::extend('tenant-regression', function ($configuration) use ($pdos) {
            $this->assertIsArray($configuration);
            $host = $configuration['host'];
            $this->assertIsString($host);
            $this->assertArrayHasKey($host, $pdos);

            return new SQLiteConnection($pdos[$host], ':memory:', '', $configuration);
        });
        $originalDefault = DB::getDefaultConnection();
        DB::setDefaultConnection('tenant');
        Cache::store('array')->flush();
        try {
            config()->set('database.connections.tenant', ['driver' => 'tenant-regression', 'database' => ':memory:', 'host' => 'tenant-a', 'password' => 'old-secret']);
            $first = $this->tenantFilter($modelConnection);
            $this->assertSame(['A', 'A'], $first->get()->pluck('marker')->all());
            $this->assertSame(2, $first->count());
            DB::purge('tenant');
            config()->set('database.connections.tenant.host', 'tenant-b');
            $second = $this->tenantFilter($modelConnection);
            $this->assertSame(['B'], $second->get()->pluck('marker')->all());
            $this->assertSame(1, $second->count());
            $this->assertNotSame($first->cacheKey(), $second->cacheKey());
            DB::connection('tenant')->enableQueryLog();
            DB::connection('tenant')->flushQueryLog();
            $hit = $this->tenantFilter($modelConnection);
            $this->assertSame($second->cacheKey(), $hit->cacheKey());
            $this->assertSame(['B'], $hit->get()->pluck('marker')->all());
            $this->assertSame(1, $hit->count());
            $equivalent = $this->tenantFilter($modelConnection === null ? 'tenant' : null);
            $this->assertSame($hit->cacheKey(), $equivalent->cacheKey(), 'Default and explicit aliases identify the same resolved connection');
            $this->assertSame(['B'], $equivalent->get()->pluck('marker')->all());
            $this->assertSame([], DB::connection('tenant')->getQueryLog());
            DB::purge('tenant');
            config()->set('database.connections.tenant.password', 'rotated-secret');
            $rotated = $this->tenantFilter($modelConnection);
            $this->assertSame($second->cacheKey(), $rotated->cacheKey(), 'Credentials are excluded from identity');
            $this->assertStringNotContainsString('secret', $rotated->cacheKey());
        } finally {
            DB::purge('tenant');
            DB::setDefaultConnection($originalDefault);
        }
    }

    #[DataProvider('modelConnections')]
    public function test_two_mysql_servers_with_the_same_database_name_and_shared_redis(?string $modelConnection): void
    {
        if (getenv('FILTERABLE_TEST_DB') !== 'mysql' || ! getenv('FILTERABLE_TEST_REDIS')) {
            $this->markTestSkipped('Requires two MySQL endpoints and Redis in the service CI job.');
        }
        $originalDefault = DB::getDefaultConnection();
        $configuration = DB::connection()->getConfig();
        $this->assertIsArray($configuration);
        config()->set('database.redis.client', 'phpredis');
        config()->set('cache.stores.tenant_regression', ['driver' => 'redis', 'connection' => 'default']);
        $cache = Cache::store('tenant_regression');
        $tag = 'tenant-regression-'.bin2hex(random_bytes(8));
        DB::setDefaultConnection('tenant');
        try {
            foreach ([3306, 3307] as $port) {
                config()->set('database.connections.tenant', array_replace($configuration, ['port' => $port]));
                DB::purge('tenant');
                DB::statement('DROP TABLE IF EXISTS cache_tenant_records');
                DB::statement('CREATE TABLE cache_tenant_records (id INTEGER PRIMARY KEY, marker VARCHAR(20))');
                DB::table('cache_tenant_records')->insert($port === 3306
                    ? [['id' => 1, 'marker' => 'A'], ['id' => 2, 'marker' => 'A']]
                    : [['id' => 1, 'marker' => 'B']]);
            }
            $keys = [];
            foreach ([3306, 3307] as $port) {
                config()->set('database.connections.tenant.port', $port);
                DB::purge('tenant');
                DB::connection()->enableQueryLog();
                DB::connection()->flushQueryLog();
                $model = (new TenantCacheRecord)->setConnection($modelConnection);
                $filter = new CacheRegressionFilter(new Request, $cache);
                $filter->enableFeature('caching')->cacheResults()->cacheCount()->cacheTags($tag);
                $filter->apply($model->newQuery());
                $this->assertSame([], DB::connection()->getQueryLog());
                $this->assertSame($configuration['database'], DB::connection()->getDatabaseName());
                $this->assertSame($port === 3306 ? ['A', 'A'] : ['B'], $filter->get()->pluck('marker')->all());
                $this->assertSame($port === 3306 ? 2 : 1, $filter->count());
                $this->assertCount(2, DB::connection()->getQueryLog());
                $keys[] = $filter->cacheKey();
                $hit = new CacheRegressionFilter(new Request, $cache);
                $hit->enableFeature('caching')->cacheResults()->cacheCount()->cacheTags($tag);
                $hit->apply($model->newQuery());
                DB::connection()->flushQueryLog();
                $this->assertSame($filter->get()->pluck('marker')->all(), $hit->get()->pluck('marker')->all());
                $this->assertSame($port === 3306 ? 2 : 1, $hit->count());
                $this->assertSame([], DB::connection()->getQueryLog());
            }
            $this->assertNotSame($keys[0], $keys[1]);
        } finally {
            foreach ([3306, 3307] as $port) {
                config()->set('database.connections.tenant.port', $port);
                DB::purge('tenant');
                DB::statement('DROP TABLE IF EXISTS cache_tenant_records');
            }
            $cache->tags([$tag])->flush();
            DB::purge('tenant');
            DB::setDefaultConnection($originalDefault);
        }
    }

    private function tenantFilter(?string $modelConnection): CacheRegressionFilter
    {
        $model = (new TenantCacheRecord)->setConnection($modelConnection);
        $filter = new CacheRegressionFilter(new Request, Cache::store('array'));
        $filter->enableFeature('caching')->cacheResults()->cacheCount();
        $filter->apply($model->newQuery());

        return $filter;
    }
}

class TenantCacheRecord extends Model
{
    protected $table = 'cache_tenant_records';
}
