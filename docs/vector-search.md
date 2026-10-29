# Optional Laravel 13 vector search

This recipe accepts a precomputed numeric vector, so it performs no AI calls and needs no AI SDK dependency. Use Laravel 13 with PostgreSQL plus pgvector. Laravel also supports MariaDB 11.7+ vector clauses, but this package's database integration fixture verifies PostgreSQL. Laravel 11/12, SQLite and ordinary MySQL users should keep ordinary filter handlers and omit this recipe.

Copy [VectorSearchFilter](../tests/Fixtures/VectorSearchFilter.php) into your application and adjust its namespace, fixed `embedding` column and dimension (the fixture uses three). It demonstrates `AsFilter`, colocated rules and whole-payload validation before any query mutation. The request shape is:

```json
{"status":"active","semantic":{"vector":[1,0,0],"threshold":0.8,"limit":10}}
```

It validates dimensions, numeric bounded values, nonzero vectors, threshold 0–1, limit 1–100, and fixed payload keys. Keep database identifiers/operators application-controlled. The handler composes `whereVectorSimilarTo('embedding', $vector, minSimilarity: $threshold)->limit($limit)` with ordinary status filters. Tenant predicates should be added with an application prefilter.

Enable pgvector and create a dimensioned column, for example:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
CREATE TABLE documents (
    id BIGSERIAL PRIMARY KEY,
    status VARCHAR(20),
    embedding vector(3)
);
CREATE INDEX documents_embedding_cosine ON documents
USING hnsw (embedding vector_cosine_ops);
```

`whereVectorSimilarTo()` orders nearest matches first by default. To replace relevance ordering, pass `order: false`, then add an application-selected `orderBy()`; do not accept arbitrary order columns from the request. Similarity requires nonzero vectors and compatible embedding dimensions/model versions.

Optionally register a consumer-supplied text-to-vector transformer with dependency-injected services. Keep paid provider calls in the consumer, test with deterministic embeddings, and validate the final vector before querying. If caching, set `cache_context` to include embedding model/version, tenant and relevant policy context. Effective vector/threshold/order/limit are included through SQL/bindings. Choose a TTL and invalidate tags when embeddings or permissions change; cache reads do not extend freshness.

The reproducible integration test is [VectorSearchTest](../tests/Integration/VectorSearchTest.php). The `services (pgsql)` CI job starts `pgvector/pgvector:pg17`, selects the PostgreSQL connection, and verifies similarity filtering, relevance order, bounded results, ordinary predicates and zero queries during `apply()`. Invalid vector tests run on every framework without contacting an AI provider.

Reference: [Laravel 13 vector similarity clauses](https://laravel.com/docs/13.x/queries#vector-similarity-clauses).
