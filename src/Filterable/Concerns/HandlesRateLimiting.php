<?php

namespace Filterable\Concerns;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\App;

trait HandlesRateLimiting
{
    /**
     * The maximum number of filters that can be applied at once.
     */
    protected int $maxFilters = 10;

    /**
     * The maximum complexity score for all filters combined.
     */
    protected int $maxComplexity = 100;

    /**
     * Complexity scores for specific filters.
     *
     * @var array<string, int>
     */
    protected array $filterComplexity = [];

    /**
     * Check if the filter request exceeds the rate limit.
     */
    protected function checkRateLimits(): bool
    {
        // Check the number of filters
        $filterCount = count($this->getFilterables());
        if ($filterCount > $this->maxFilters) {
            if (method_exists($this, 'logWarning')) {
                $this->logWarning('Too many filters applied', [
                    'applied' => $filterCount,
                    'maximum' => $this->maxFilters,
                ]);
            }

            return false;
        }

        // Check the complexity score
        $complexityScore = $this->calculateComplexity();
        if ($complexityScore > $this->maxComplexity) {
            if (method_exists($this, 'logWarning')) {
                $this->logWarning('Filter request too complex', [
                    'complexity' => $complexityScore,
                    'maximum' => $this->maxComplexity,
                ]);
            }

            return false;
        }

        // Use Laravel's rate limiter for throttling complex requests
        $limiter = App::make(RateLimiter::class);
        $key = 'filter:'.md5($this->request->ip().'|'.get_class($this));

        if ($limiter->tooManyAttempts($key, 60)) {
            if (method_exists($this, 'logWarning')) {
                $this->logWarning('Rate limit exceeded for complex filter', [
                    'ip' => $this->request->ip(),
                    'complexity' => $complexityScore,
                ]);
            }

            return false;
        }

        // Add to the rate limiter based on complexity
        $limiter->hit($key, ceil($complexityScore / 10));

        return true;
    }

    /**
     * Calculate the complexity score of the current filter request.
     */
    protected function calculateComplexity(): int
    {
        $filterables = $this->getFilterables();
        $complexity = 0;

        foreach ($filterables as $filter => $value) {
            // Base complexity is 1 per filter
            $filterComplexity = 1;

            // Add specific filter complexity if defined
            if (isset($this->filterComplexity[$filter])) {
                $filterComplexity = $this->filterComplexity[$filter];
            }

            // Adjust complexity based on value (arrays are more complex)
            if (is_array($value)) {
                $filterComplexity *= count($value);
            }

            $complexity += $filterComplexity;
        }

        return $complexity;
    }

    /**
     * Set the maximum number of filters.
     */
    public function setMaxFilters(int $max): self
    {
        $this->maxFilters = $max;

        return $this;
    }

    /**
     * Set the maximum complexity score.
     */
    public function setMaxComplexity(int $max): self
    {
        $this->maxComplexity = $max;

        return $this;
    }

    /**
     * Set complexity scores for specific filters.
     */
    public function setFilterComplexity(array $complexityMap): self
    {
        $this->filterComplexity = $complexityMap;

        return $this;
    }
}
