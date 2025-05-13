<?php

namespace Filterable\Concerns;

use Filterable\Contracts\Filter;
use Psr\Log\LoggerInterface;

trait InteractsWithLogging
{
    /**
     * Indicates if logging should be used.
     */
    protected static bool $shouldLog = false;

    /**
     * The logger instance.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Log an informational message.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if (self::shouldLog()) {
            $this->getLogger()->info($message, $context);
        }
    }

    /**
     * Log a debug message.
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if (self::shouldLog()) {
            $this->getLogger()->debug($message, $context);
        }
    }

    /**
     * Log a warning message.
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if (self::shouldLog()) {
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

    /**
     * Enable logging.
     */
    public static function enableLogging(): void
    {
        self::$shouldLog = true;
    }

    /**
     * Disable logging.
     */
    public static function disableLogging(): void
    {
        self::$shouldLog = false;
    }

    /**
     * Check if logging should be used.
     */
    public static function shouldLog(): bool
    {
        return self::$shouldLog;
    }
}
