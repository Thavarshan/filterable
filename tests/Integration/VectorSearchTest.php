<?php

namespace Filterable\Tests\Integration;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\Fixtures\VectorSearchFilter;
use Filterable\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VectorSearchTest extends TestCase
{
    public function test_invalid_vectors_fail_before_query_mutation_on_all_frameworks(): void
    {
        foreach ([[0, 0, 0], [1, 2], ['invalid', 0, 1]] as $vector) {
            $filter = new VectorSearchFilter(new Request(['status' => 'active', 'semantic' => ['vector' => $vector]]));
            $query = MockFilterable::query();
            try {
                $filter->apply($query);
                $this->fail('Invalid vector accepted');
            } catch (ValidationException) {
                $this->assertSame([], $query->getBindings());
            }
        }
        foreach ([['threshold' => 1.1], ['threshold' => -0.1], ['limit' => 0], ['limit' => 101], ['column' => 'untrusted']] as $invalid) {
            $filter = new VectorSearchFilter(new Request(['semantic' => array_merge(['vector' => [1, 0, 0]], $invalid)]));
            $query = MockFilterable::query();
            try {
                $filter->apply($query);
                $this->fail('Invalid vector policy accepted');
            } catch (ValidationException) {
                $this->assertSame([], $query->getBindings());
            }
        }
    }

    public function test_pgvector_similarity_ranking_limit_and_ordinary_predicates(): void
    {
        if (getenv('FILTERABLE_TEST_DB') !== 'pgsql') {
            $this->markTestSkipped('Requires the PostgreSQL pgvector / Laravel 13 service job.');
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('DROP TABLE IF EXISTS vector_documents');
        DB::statement('CREATE TABLE vector_documents (id SERIAL PRIMARY KEY, status VARCHAR(20), embedding vector(3))');
        try {
            DB::table('vector_documents')->insert([
                ['id' => 1, 'status' => 'active', 'embedding' => '[1,0,0]'],
                ['id' => 2, 'status' => 'active', 'embedding' => '[0.8,0.2,0]'],
                ['id' => 3, 'status' => 'inactive', 'embedding' => '[1,0,0]'],
                ['id' => 4, 'status' => 'active', 'embedding' => '[0,1,0]'],
            ]);
            $model = new class extends Model
            {
                protected $table = 'vector_documents';
            };
            foreach ([1, 2] as $limit) {
                $filter = new VectorSearchFilter(new Request(['status' => 'active', 'semantic' => [
                    'vector' => [1, 0, 0], 'threshold' => 0.8, 'limit' => $limit,
                ]]));
                DB::enableQueryLog();
                DB::flushQueryLog();
                $query = $filter->apply($model->newQuery());
                $this->assertSame([], DB::getQueryLog());
                $this->assertSame(array_slice([1, 2], 0, $limit), $query->pluck('id')->all());
            }
        } finally {
            DB::statement('DROP TABLE vector_documents');
        }
    }
}
