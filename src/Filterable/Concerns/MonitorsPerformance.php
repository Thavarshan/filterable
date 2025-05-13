<?php

namespace Filterable\Concerns;

trait MonitorsPerformance
{
    /**
     * The start time of filter application.
     */
    protected ?float $startTime = null;

    /**
     * The end time of filter application.
     */
    protected ?float $endTime = null;

    /**
     * Performance metrics for the filter.
     *
     * @var array<string, mixed>
     */
    protected array $metrics = [];

    /**
     * Start timing the filter application.
     */
    protected function startTiming(): void
    {
        $this->startTime = microtime(true);
    }

    /**
     * End timing the filter application.
     */
    protected function endTiming(): void
    {
        $this->endTime = microtime(true);
        $this->metrics['execution_time'] = $this->endTime - $this->startTime;

        if (method_exists($this, 'logInfo')) {
            $this->logInfo('Filter executed', [
                'execution_time' => $this->metrics['execution_time'],
                'memory_usage' => memory_get_usage(true),
                'filter_count' => count($this->getFilterables()),
            ]);
        }
    }

    /**
     * Add a custom metric.
     */
    public function addMetric(string $key, mixed $value): self
    {
        $this->metrics[$key] = $value;

        return $this;
    }

    /**
     * Get all performance metrics.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get the execution time of the filter application.
     */
    public function getExecutionTime(): ?float
    {
        return $this->metrics['execution_time'] ?? null;
    }
}
