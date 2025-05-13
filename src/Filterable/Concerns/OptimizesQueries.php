<?php

namespace Filterable\Concerns;

trait OptimizesQueries
{
    /**
     * The columns to select from the database.
     *
     * @var array<string>|null
     */
    protected ?array $selectColumns = null;

    /**
     * Whether to defer loading relationships.
     */
    protected bool $deferRelationships = false;

    /**
     * Relationships to eager load.
     *
     * @var array<string>
     */
    protected array $eagerLoadRelations = [];

    /**
     * Configure the query for optimal performance.
     */
    protected function optimizeQuery(): void
    {
        // Select only needed columns
        if (! is_null($this->selectColumns)) {
            $this->builder->select($this->selectColumns);
        }

        // Add eager loading for relationships
        if (! empty($this->eagerLoadRelations)) {
            $this->builder->with($this->eagerLoadRelations);
        }

        // Use query chunking for large datasets if configured
        if (isset($this->options['chunk_size']) && is_numeric($this->options['chunk_size'])) {
            // We'll set a flag to use chunking when executing
            $this->options['use_chunking'] = true;
        }
    }

    /**
     * Set specific columns to select to reduce data transfer.
     */
    public function select(array $columns): self
    {
        $this->selectColumns = $columns;

        return $this;
    }

    /**
     * Eager load relationships to prevent N+1 queries.
     */
    public function with(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->eagerLoadRelations = array_merge($this->eagerLoadRelations, $relations);

        return $this;
    }

    /**
     * Enable chunked processing for large datasets.
     */
    public function chunkSize(int $size): self
    {
        $this->options['chunk_size'] = $size;

        return $this;
    }

    /**
     * Use a database index hint for better performance.
     */
    public function useIndex(string $index): self
    {
        // This will add an index hint in MySQL
        // Note: This is database-specific and may need adaptation
        $this->builder->from($this->builder->getQuery()->from." USE INDEX ({$index})");

        return $this;
    }
}
