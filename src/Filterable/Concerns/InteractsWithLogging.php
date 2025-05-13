<?php

namespace Filterable\Concerns;

use Filterable\Contracts\Filter;
use Psr\Log\LoggerInterface;

trait InteractsWithLogging
{
    /**
     * The logger instance.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Log an informational message.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->hasFeature('logging')) {
            $this->getLogger()->info($message, $context);
        }
    }

    /**
     * Log a debug message.
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->hasFeature('logging')) {
            $this->getLogger()->debug($message, $context);
        }
    }

    /**
     * Log a warning message.
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if ($this->hasFeature('logging')) {
            $this->getLogger()->warning($message, $context);
        }
    }

    /**
     * Set the Logger instance.
     */
    public function setLogger(LoggerInterface $logger): Filter
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get the Logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? app(LoggerInterface::class);
    }
}
