[![Filterable](./assets/Banner.png)](https://github.com/Thavarshan/filterable)

# About Filterable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Tests](https://github.com/Thavarshan/filterable/actions/workflows/tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/filterable/actions/workflows/tests.yml)
[![Lint](https://github.com/Thavarshan/filterable/actions/workflows/lint.yml/badge.svg)](https://github.com/Thavarshan/filterable/actions/workflows/lint.yml)
[![CodeQL](https://github.com/Thavarshan/filterable/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Thavarshan/filterable/actions/workflows/github-code-scanning/codeql)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/packagist/php-v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![License](https://img.shields.io/packagist/l/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![GitHub Stars](https://img.shields.io/github/stars/Thavarshan/filterable.svg?style=social&label=Stars)](https://github.com/Thavarshan/filterable/stargazers)

The `Filterable` package provides a robust, feature-rich solution for applying dynamic filters to Laravel's Eloquent queries. With a modular, trait-based architecture, it supports advanced features like intelligent caching, user-specific filtering, performance monitoring, memory management, and much more. It's suitable for applications of any scale, from simple blogs to complex enterprise-level data platforms.

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x

## Features

- **Dynamic Filtering**: Apply filters based on request parameters with ease
- **Modular Architecture**: Customize your filter implementation using traits
- **Smart Caching**: Both simple and intelligent caching strategies with automatic cache key generation
- **User-Specific Filtering**: Easily implement user-scoped filters
- **Rate Limiting**: Control filter complexity and prevent abuse
- **Validation**: Validate filter inputs before processing
- **Permission Control**: Apply permission-based access to specific filters
- **Performance Monitoring**: Track execution time and query performance
- **Memory Management**: Optimize memory usage for large datasets with lazy loading and chunking
- **Query Optimization**: Intelligent query building with column selection and relationship loading
- **Logging**: Comprehensive logging capabilities for debugging and monitoring
- **Filter Chaining**: Chain multiple filter operations with a fluent API
- **Value Transformation**: Transform input values before applying filters
- **Custom Pre-Filters**: Register filters to run before the main filters
- **Comprehensive Debugging**: Detailed debug information about applied filters and query execution
- **Conditional Execution**: Use Laravel's conditionable trait for conditional filter application
- **Smart Error Handling**: Graceful handling of filtering exceptions
- **Flexible State Management**: Monitor and manage the filter execution state
- **Chainable Configuration**: Fluent API for configuration with method chaining

## Installation

To integrate the `Filterable` package into your Laravel project, install it via Composer:

```bash
composer require jerome/filterable
```

The package automatically registers its service provider with Laravel's service container through auto-discovery (Laravel 5.5+).

For older Laravel versions, manually register the `FilterableServiceProvider` in your `config/app.php` file:

```php
'providers' => [
    // Other service providers...
    Filterable\Providers\FilterableServiceProvider::class,
],
```

## Usage

### Creating a Filter Class

Create a new filter class using the Artisan command:

```bash
php artisan make:filter PostFilter
```

This command supports several options:

| Option | Shortcut | Description |
|--------|----------|-------------|
| `--basic` | `-b` | Creates a basic filter class with minimal functionality |
| `--model=ModelName` | `-m ModelName` | Generates a filter for the specified model |
| `--force` | `-f` | Creates the class even if the filter already exists |

Examples:

```bash
# Create a basic filter
php artisan make:filter PostFilter --basic

# Create a filter for a specific model
php artisan make:filter PostFilter --model=Post

# Force creation of a filter
php artisan make:filter PostFilter --force

# Combine options
php artisan make:filter PostFilter --model=Post --force
```

The command generates a filter class in the `app/Filters` directory. Extend the base `Filter` class to implement your specific filtering logic:

```php
namespace App\Filters;

use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

class PostFilter extends Filter
{
    protected array $filters = ['status', 'category'];

    /**
     * Enable specific features for this filter.
     */
    public function __construct(Request $request, ?Cache $cache = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($request, $cache, $logger);

        // Enable the features you need
        $this->enableFeatures([
            'validation',
            'caching',
            'logging',
            'performance',
        ]);
    }

    protected function status(string $value): Builder
    {
        return $this->builder->where('status', $value);
    }

    protected function category(int $value): Builder
    {
        return $this->builder->where('category_id', $value);
    }
}
```

#### Adding Custom Filters

To add a new filter, define a method within your filter class using **camelCase** naming, and register it in the `$filters` array:

```php
protected array $filters = ['last_published_at'];

protected function lastPublishedAt(string $value): Builder
{
    return $this->builder->where('last_published_at', $value);
}
```

### Implementing the `Filterable` Trait and Interface

Apply the `Filterable` interface and trait to your Eloquent models:

```php
namespace App\Models;

use Filterable\Interfaces\Filterable as FilterableInterface;
use Filterable\Traits\Filterable as FilterableTrait;
use Illuminate\Database\Eloquent\Model;

class Post extends Model implements FilterableInterface
{
    use FilterableTrait;
}
```

### Applying Filters

Basic usage:

```php
use App\Models\Post;
use App\Filters\PostFilter;

$filter = new PostFilter(request(), cache(), logger());
$posts = Post::filter($filter)->get();
```

In a controller:

```php
use App\Models\Post;
use App\Filters\PostFilter;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request, PostFilter $filter)
    {
        $query = Post::filter($filter);

        $posts = $request->has('paginate')
            ? $query->paginate($request->query('per_page', 20))
            : $query->get();

        return response()->json($posts);
    }
}
```

### Laravel 12 Support

For Laravel 12, which has moved to a more minimal initial setup, make sure to follow these additional steps:

1. **Service Registration**: If you're using a minimal Laravel 12 application, you may need to manually register the service provider in your `bootstrap/providers.php` file:

```php
return [
    // Other service providers...
    Filterable\Providers\FilterableServiceProvider::class,
];
```

2. **Invokable Controllers**: If you're using Laravel 12's invokable controllers, here's how to apply filters:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Filters\PostFilter;
use Illuminate\Http\Request;

class PostIndexController extends Controller
{
    public function __invoke(Request $request, PostFilter $filter)
    {
        $query = Post::filter($filter);

        $posts = $request->has('paginate')
            ? $query->paginate($request->query('per_page', 20))
            : $query->get();

        return response()->json($posts);
    }
}
```

3. **Route Registration**: Using the new routing style in Laravel 12:

```php
use App\Http\Controllers\PostIndexController;

Route::get('/posts', PostIndexController::class);
```

### Advanced Features

#### Feature Management

Selectively enable features for your filter:

```php
// Enable individual features
$filter->enableFeature('validation');
$filter->enableFeature('caching');

// Enable multiple features at once
$filter->enableFeatures([
    'validation',
    'caching',
    'logging',
    'performance',
]);

// Disable a feature
$filter->disableFeature('caching');

// Check if a feature is enabled
if ($filter->hasFeature('caching')) {
    // Do something
}
```

##### Available Features

The Filterable package supports the following features that can be enabled or disabled:

| Feature | Description |
|---------|-------------|
| `validation` | Validates filter inputs before applying them |
| `permissions` | Enables permission-based access to filters |
| `rateLimit` | Controls filter complexity and prevents abuse |
| `caching` | Caches query results for improved performance |
| `logging` | Provides comprehensive logging capabilities |
| `performance` | Monitors execution time and query performance |
| `optimization` | Optimizes queries with selective columns and eager loading |
| `memoryManagement` | Optimizes memory usage for large datasets |
| `filterChaining` | Enables fluent chaining of multiple filter operations |
| `valueTransformation` | Transforms input values before applying filters |

Each feature can be enabled independently based on your specific needs:

```php
// Enable all features
$filter->enableFeatures([
    'validation',
    'permissions',
    'rateLimit',
    'caching',
    'logging',
    'performance',
    'optimization',
    'memoryManagement',
    'filterChaining',
    'valueTransformation',
]);
```

#### User-Scoped Filtering

Apply filters that are specific to the authenticated user:

```php
$filter->forUser($request->user());
```

#### Pre-Filters

Apply pre-filters that run before the main filters:

```php
$filter->registerPreFilters(function (Builder $query) {
    return $query->where('published', true);
});
```

#### Validation

Set validation rules for your filter inputs:

```php
$filter->setValidationRules([
    'status' => 'required|in:active,inactive',
    'category_id' => 'sometimes|integer|exists:categories,id',
]);

// Add custom validation messages
$filter->setValidationMessages([
    'status.in' => 'Status must be either active or inactive',
]);
```

#### Permission Control

Define permission requirements for specific filters:

```php
$filter->setFilterPermissions([
    'admin_only_filter' => 'admin',
    'editor_filter' => ['editor', 'admin'],
]);

// Implement the permission check in your filter class
protected function userHasPermission(string|array $permission): bool
{
    if (is_array($permission)) {
        return collect($permission)->contains(fn ($role) => $this->forUser->hasRole($role));
    }

    return $this->forUser->hasRole($permission);
}
```

#### Rate Limiting

Control the complexity of filter requests:

```php
// Set the maximum number of filters that can be applied at once
$filter->setMaxFilters(10);

// Set the maximum complexity score for all filters combined
$filter->setMaxComplexity(100);

// Define complexity scores for specific filters
$filter->setFilterComplexity([
    'complex_filter' => 10,
    'simple_filter' => 1,
]);
```

#### Memory Management

Optimize memory usage for large datasets:

```php
// Process a query with lazy loading
$posts = $filter->lazy()->each(function ($post) {
    // Process each post with minimal memory usage
});

// Use chunking for large datasets
$filter->chunk(1000, function ($posts) {
    // Process posts in chunks of 1000
});

// Map over query results without loading all records
$result = $filter->map(function ($post) {
    return $post->title;
});

// Filter results without loading all records
$result = $filter->filter(function ($post) {
    return $post->status === 'active';
});

// Reduce results without loading all records
$total = $filter->reduce(function ($carry, $post) {
    return $carry + $post->views;
}, 0);

// Get a lazy collection with custom chunk size
$lazyCollection = $filter->lazy(500);

// Process each item with minimal memory usage
$filter->lazyEach(function ($item) {
    // Process item
}, 500);

// Create a generator to iterate with minimal memory
foreach ($filter->cursor() as $item) {
    // Process item
}
```

#### Query Optimization

Optimize database queries:

```php
// Select only needed columns
$filter->select(['id', 'title', 'status']);

// Eager load relationships
$filter->with(['author', 'comments']);

// Set chunk size for large datasets
$filter->chunkSize(1000);

// Use a database index hint
$filter->useIndex('idx_posts_status');
```

#### Caching

Configure caching behavior:

```php
// Set cache expiration time (in minutes)
$filter->setCacheExpiration(60);

// Manually clear the cache
$filter->clearCache();

// Use tagged cache for better invalidation
$filter->cacheTags(['posts', 'api']);

// Enable specific caching modes
$filter->cacheResults(true);
$filter->cacheCount(true);

// Get the number of items with caching
$count = $filter->count();

// Clear related caches when models change
$filter->clearRelatedCaches(Post::class);

// Get SQL query without executing it
$sql = $filter->toSql();
```

#### Logging

Configure and use logging:

```php
// Set a custom logger
$filter->setLogger($customLogger);

// Get the current logger
$logger = $filter->getLogger();

// Log at different levels
$filter->logInfo("Applying filter", ['filter' => 'status']);
$filter->logDebug("Filter details", ['value' => $value]);
$filter->logWarning("Potential issue", ['problem' => 'description']);

// Logging is automatically handled if enabled
// You can also add custom logging in your filter methods:
protected function customFilter($value): Builder
{
    $this->logInfo("Applying custom filter with value: {$value}");

    return $this->builder->where('custom_field', $value);
}
```

#### Performance Monitoring

Track and analyze filter performance:

```php
// Get performance metrics after applying filters
$metrics = $filter->getMetrics();

// Add custom metrics
$filter->addMetric('custom_metric', $value);

// Get execution time
$executionTime = $filter->getExecutionTime();
```

#### Filter Chaining

Chain multiple filter operations with a fluent API:

```php
$filter->where('status', 'active')
       ->whereIn('category_id', [1, 2, 3])
       ->whereNotIn('tag_id', [4, 5])
       ->whereBetween('created_at', [$startDate, $endDate])
       ->orderBy('created_at', 'desc');
```

#### Value Transformation

Transform filter values before applying them:

```php
// Register a transformer for a filter
$filter->registerTransformer('date', function ($value) {
    return Carbon::parse($value)->toDateTimeString();
});

// Register a transformer for an array of values
$arrayTransformer = function($values) {
    return array_map(fn($value) => strtolower($value), $values);
};
$filter->registerTransformer('tags', $arrayTransformer);
```

#### Conditional Execution

Use Laravel's conditionable trait for conditional filter application:

```php
// Only apply a filter if a condition is met
$filter->when($request->has('status'), function ($filter) use ($request) {
    $filter->where('status', $request->status);
});

// Apply one filter or another based on a condition
$filter->when($request->has('sort'),
    function ($filter) use ($request) {
        $filter->orderBy($request->sort);
    },
    function ($filter) {
        $filter->orderBy('created_at', 'desc');
    }
);
```

#### State Management

Monitor and manage the filter execution state:

```php
// Check the current state
if ($filter->getDebugInfo()['state'] === 'applied') {
    // Process results
}

// Reset the filter to its initial state
$filter->reset();
```

#### Debug Information

Get detailed information about the applied filters:

```php
$debugInfo = $filter->getDebugInfo();

// Debug info includes:
// - Current state
// - Applied filters
// - Enabled features
// - Query options
// - SQL query and bindings
// - Performance metrics (if enabled)
```

#### Error Handling

Customize exception handling for your filters:

```php
class MyFilter extends Filter
{
    protected function handleFilteringException(Throwable $exception): void
    {
        // Log the exception
        $this->logWarning('Filter exception', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally rethrow specific exceptions
        if ($exception instanceof MyCustomException) {
            throw $exception;
        }

        // Otherwise, let the parent handle it
        parent::handleFilteringException($exception);
    }
}
```

### Complete Example

```php
use App\Models\Post;
use App\Filters\PostFilter;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request, PostFilter $filter)
    {
        // Enable features
        $filter->enableFeatures([
            'validation',
            'caching',
            'logging',
            'performance',
        ]);

        // Set validation rules
        $filter->setValidationRules([
            'status' => 'sometimes|in:active,inactive',
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        // Apply user scope
        $filter->forUser($request->user());

        // Apply pre-filters
        $filter->registerPreFilters(function ($query) {
            return $query->where('published', true);
        });

        // Set caching options
        $filter->setCacheExpiration(30);
        $filter->cacheTags(['posts', 'api']);

        // Apply custom filter chain
        $filter->where('is_featured', true)
               ->orderBy('created_at', 'desc');

        // Apply filters to the query
        $query = Post::filter($filter);

        // Get paginated results
        $posts = $request->has('paginate')
            ? $query->paginate($request->query('per_page', 20))
            : $query->get();

        // Get performance metrics if needed
        $metrics = null;
        if ($filter->hasFeature('performance')) {
            $metrics = $filter->getMetrics();
        }

        return response()->json([
            'data' => $posts,
            'metrics' => $metrics,
        ]);
    }
}
```

## Frontend Usage

Send filter parameters as query parameters:

```typescript
// Filter posts by status
const response = await fetch('/posts?status=active');

// Combine multiple filters
const response = await fetch('/posts?status=active&category_id=2&is_featured=1');
```

## Testing

Testing your filters using PHPUnit:

```php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Post;
use App\Filters\PostFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class PostFilterTest extends TestCase
{
    use RefreshDatabase;

    public function testFiltersPostsByStatus(): void
    {
        $activePost = Post::factory()->create(['status' => 'active']);
        $inactivePost = Post::factory()->create(['status' => 'inactive']);

        $filter = new PostFilter(new Request(['status' => 'active']));
        $filteredPosts = Post::filter($filter)->get();

        $this->assertTrue($filteredPosts->contains($activePost));
        $this->assertFalse($filteredPosts->contains($inactivePost));
    }

    public function testRateLimitingRejectsComplexQueries(): void
    {
        // Create a filter with too many parameters
        $filter = new PostFilter(new Request([
            'param1' => 'value1',
            'param2' => 'value2',
            // ... many more parameters
        ]));

        $filter->enableFeature('rateLimit');
        $filter->setMaxFilters(5);

        // Apply the filter and check if rate limiting was triggered
        $result = Post::filter($filter)->get();

        // Assert that no results were returned due to rate limiting
        $this->assertEmpty($result);
    }
}
```

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Contributing

Contributions are welcome and greatly appreciated! If you have suggestions to make this package better, please fork the repository and create a pull request, or open an issue with the tag "enhancement".

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/amazing-feature`)
3. Commit your Changes (`git commit -m 'Add some amazing-feature'`)
4. Push to the Branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Authors

- **[Jerome Thayananthajothy]** - *Initial work* - [Thavarshan](https://github.com/Thavarshan)

See also the list of [contributors](https://github.com/Thavarshan/filterable/contributors) who participated in this project.

## Acknowledgments

- Hat tip to Spatie for their [query builder](https://github.com/spatie/laravel-query-builder) package, which inspired this project.
