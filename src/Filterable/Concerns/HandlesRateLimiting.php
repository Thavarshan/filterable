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
        $key = $this->resolveRateLimitKey();
        $maxAttempts = $this->resolveRateLimitMaxAttempts();
        $windowSeconds = $this->resolveRateLimitWindowSeconds($complexityScore);
        $decaySeconds = $this->resolveRateLimitDecaySeconds($complexityScore);

        if ($limiter->tooManyAttempts($key, $maxAttempts, $windowSeconds)) {
            if (method_exists($this, 'logWarning')) {
                $this->logWarning('Rate limit exceeded for complex filter', [
                    'ip' => $this->request->ip(),
                    'user' => $this->resolveRateLimitUserIdentifier(),
                    'complexity' => $complexityScore,
                ]);
            }

            return false;
        }

        // Add to the rate limiter based on complexity
        $limiter->hit($key, $decaySeconds);

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
     * Resolve the key used for rate limiting the current filter.
     */
    protected function resolveRateLimitKey(): string
    {
        $parts = [
            $this->request->ip() ?? 'unknown',
            static::class,
        ];

        if ($userIdentifier = $this->resolveRateLimitUserIdentifier()) {
            $parts[] = 'user:'.$userIdentifier;
        }

        return 'filter:'.md5(implode('|', $parts));
    }

    /**
     * Resolve the user identifier to scope rate limiting, if available.
     */
    protected function resolveRateLimitUserIdentifier(): ?string
    {
        if (! property_exists($this, 'forUser') || $this->forUser === null) {
            return null;
        }

        if (! method_exists($this->forUser, 'getAuthIdentifier')) {
            return null;
        }

        $identifier = $this->forUser->getAuthIdentifier();

        return is_scalar($identifier) ? (string) $identifier : null;
    }

    /**
     * Resolve the maximum number of attempts allowed within the decay window.
     */
    protected function resolveRateLimitMaxAttempts(): int
    {
        return 60;
    }

    /**
     * Resolve the window (in seconds) for the rate limiter.
     */
    protected function resolveRateLimitWindowSeconds(int $complexityScore): int
    {
        return 60;
    }

    /**
     * Resolve the decay (in seconds) applied to each rate limiter hit.
     */
    protected function resolveRateLimitDecaySeconds(int $complexityScore): int
    {
        return max(1, (int) ceil($complexityScore / 10));
    }
}
