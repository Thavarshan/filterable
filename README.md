[![Filterable](https://i.ibb.co/VQth3zr/Banner.png)](https://github.com/Thavarshan/filterable)

# About Filterable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)
[![Tests](https://github.com/Thavarshan/filterable/actions/workflows/run-tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/filterable/actions/workflows/run-tests.yml)
[![Check & fix styling](https://github.com/Thavarshan/filterable/actions/workflows/php-cs-fixer.yml/badge.svg?label=code%20style&branch=main)](https://github.com/Thavarshan/filterable/actions/workflows/php-cs-fixer.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/filterable.svg)](https://packagist.org/packages/jerome/filterable)

The `Filter` class provides a flexible and powerful way to apply dynamic filters to Laravel's Eloquent queries. It supports caching, user-specific filtering, and custom filter methods, making it suitable for a wide range of applications, from simple blogs to complex data-driven platforms.

## Features

- **Dynamic Filtering**: Apply filters based on request parameters with ease.
- **Caching**: Improve performance by caching query results.
- **User-specific Filtering**: Easily implement filters that depend on the authenticated user.
- **Custom Filter Methods**: Extend the class to add your own filter methods.

## Installation

To integrate the `Filterable` package into your Laravel project, you can install it via Composer. Run the following command in your project directory:

```bash
composer require jerome/filterable
```

Upon installation, the package should automatically register its service provider with Laravel's service container, making its features readily available throughout your application. This leverages Laravel's package auto-discovery mechanism, which is supported in Laravel 5.5 and later versions.

If you are using a version of Laravel that does not support package auto-discovery, you will need to manually register the `FilterableServiceProvider` in your `config/app.php` file, under the `providers` array:

```php
'providers' => [
    // Other service providers...

    Filterable\Providers\FilterableServiceProvider::class,
],
```

This step is typically not necessary for modern Laravel installations, as auto-discovery should handle it for you.

After installation and registration, you're ready to use the `Filterable` features to enhance your Laravel application's data querying capabilities.

## Usage

### Creating a Filter Class

You can create a new filter class using the following Artisan command:

```bash
php artisan make:filter PostFilter
```

This command will generate a new filter class in the `app/Filters` directory. You can then customise this class to add your own filter methods.

The `Filter` class is a base class that provides the core functionality for applying filters to Eloquent queries. You can extend this class to create your own filter classes tailored to your specific models. To use the `Filter` class, you first need to extend it to create your own filter class tailored to your specific model. Here's a basic example for a `Post` model:

```php
namespace App\Filters;

use Filterable\Filter;
use Illuminate\Database\Eloquent\Builder;

class PostFilter extends Filter
{
    protected array $filters = ['status', 'category'];

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

To add a new filter, simply define a new method within your custom filter class. This method should adhere to PHP's **camelCase** naming convention and be named descriptively based on the filter's purpose. Once you've implemented the method, ensure to register its name in the `$filters` array to activate it. Here's how you can do it:

```php
namespace App\Filters;

use Filterable\Filter;

class PostFilter extends Filter
{
    protected array $filters = ['last_published_at'];

    protected function lastPublishedAt(int $value): Builder
    {
        return $this->builder->where('last_published_at', $value);
    }
}
```

In this example, a new filter `lastPublishedAt` is created in the PostFilter class. The filter name `last_published_at` is registered in the `$filters` array.

### Implementing the `Filterable` Trait and `Filterable` Interface

To use the `Filter` class in your Eloquent models, you need to implement the `Filterable` interface and use the `Filterable` trait. Here's an example for a `Post` model:

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

> **Note**: The `Filterable` interface and `Filterable` trait are included in the package and should be used in your models to enable filtering. The `Filterable` interface is optional but recommended for consistency.

### Applying Filters

You can apply filters to your Eloquent queries like so:

```php
use App\Models\Post;

$filter = new PostFilter(request(), cache());
$posts = Post::filter($filter)->get();
```

### Applying Filters in Controllers

You can apply your custom filters in your controller methods like so:

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

### Applying Filters Scoped to the Authenticated User

You can also apply filters that are specific to the authenticated user. The `forUser` method sets the user for which the filters should be applied:

```php
use App\Models\Post;
use App\Filters\PostFilter;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request, PostFilter $filter)
    {
        $filter->forUser($request->user());

        $query = Post::filter($filter);

        $posts = $request->has('paginate')
            ? $query->paginate($request->query('per_page', 20))
            : $query->get();

        return response()->json($posts);
    }
}
```

### Applying Pre-Filters to run before the main filters

You can also apply pre-filters that run before the main filters. The `registerPreFilters` method sets the pre-filters that should be applied:

```php
use App\Models\Post;
use App\Filters\PostFilter;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class PostController extends Controller
{
    public function index(Request $request, PostFilter $filter)
    {
        $filter->registerPreFilters(function (Builder $query) {
            return $query->where('published', true);
        });

        $query = Post::filter($filter);

        $posts = $request->has('paginate')
            ? $query->paginate($request->query('per_page', 20))
            : $query->get();

        return response()->json($posts);
    }
}
```

### Using Filters on the Frontend

You can use filters on the frontend by sending a request with query parameters. For example, to filter posts by status, you can send a request like this:

```typescript
const response = await fetch('/posts?status=active');

const data = await response.json();
```

This request will return all posts with the status `active`.

You can also string together all the filters you want to apply. For example, to filter posts by status and category, you can send a request like this:

```typescript
const response = await fetch('/posts?status=active&category_id=2');

const data = await response.json();
```

This request will return all posts with the status `active` and associated with the category of ID `2`.

> **Note**: Any query parameters that do not match the filter names will be ignored.

### Caching

In your filter class, you can control caching by using the `enableCaching` static method. Set the `$useCache` static property to `true` to enable caching, or `false` to disable it. You can also customise the duration of the cache by modifying the `$cacheExpiration` property.`

#### Enabling and Disabling Caching

- **Enable Caching**: To start caching, ensure that caching is enabled. This is typically done during the setup or dynamically based on the application context, such as only enabling caching in a production environment to improve performance and reduce database load.

```php
// AppServiceProvider.php

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot(): void
{
    // Enable caching globally through methods...
    Filter::enableCaching();
}
```

- **Disable Caching**: If you need to turn off caching temporarily, for example, during development to ensure fresh data is loaded on each request and to aid in debugging, you can disable it:

```php
// AppServiceProvider.php

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot(): void
{
    // Disable caching globally through methods...
    Filter::disableCaching();
}
```

This configuration allows you to manage caching settings centrally from the `AppServiceProvider`. Adjusting caching behavior based on the environment or specific scenarios helps optimize performance and resource utilization effectively.

```php
namespace App\Filters;

use Filterable\Filter;

class PostFilter extends Filter
{
    protected array $filters = ['last_published_at'];

    protected function lastPublishedAt(int $value): Builder
    {
        return $this->builder->where('last_published_at', $value);
    }
}

$filter = new PostFilter(request(), cache());

// Control caching
$filter->setCacheExpiration(1440); // Cache duration in minutes
```

Certainly! Here’s a detailed usage guide section that explains how to utilize the logging functionality in the `Filter` class. This guide is designed to help developers understand and implement logging within the context of filtering operations effectively.

### Logging

The `Filter` class incorporates robust logging capabilities to aid in debugging and monitoring the application of filters to query builders. This functionality is crucial for tracing issues, understanding filter impacts, and ensuring the system behaves as expected.

#### Configuring the Logger

1. **Setting Up Logger**: Before you can log any activities, you must provide a logger instance to the `Filter` class. This logger should conform to the `Psr\Log\LoggerInterface`. Typically, this is set up in the constructor or through a setter method if the logger might change during the lifecycle of the application.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a logger instance
$logger = new Logger('name');
$logger->pushHandler(new StreamHandler('path/to/your.log', Logger::WARNING));

// Set the logger to the filter class
$filter->setLogger($logger);
```

2. **Dependency Injection**: If you are using Laravel, you can leverage its service container to automatically inject the logger into your `Filter` class.

```php
// In a service provider or similar setup
$this->app->when(Filter::class)
    ->needs(LoggerInterface::class)
    ->give(function () {
        return new Logger('name', [new StreamHandler('path/to/your.log', Logger::WARNING)]);
    });
```

#### Setting Up Logger with a Custom Channel

You can set up a specific logging channel for your `Filter` class either by configuring it directly in the logger setup or by defining it in Laravel’s logging configuration and then injecting it. Here’s how to do it in both ways:

1. **Direct Configuration**:

   - Directly create a logger with a specific channel and handler. This method is straightforward and gives you full control over the logger’s configuration:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a logger instance for the Filter class with a custom channel
$logger = new Logger('filter');
$logger->pushHandler(new StreamHandler(storage_path('logs/filter.log'), Logger::DEBUG));

// Set the logger to the filter class
$filter->setLogger($logger);
```

2. **Using Laravel's Logging Configuration**:

   - Laravel allows you to define custom channels in its logging configuration file (`config/logging.php`). You can define a specific channel for the `Filter` class there and then retrieve it using the `Log` facade:

```php
// In config/logging.php

'channels' => [
    'filter' => [
        'driver' => 'single',
        'path' => storage_path('logs/filter.log'),
        'level' => 'debug',
    ],
],
```

- Now, you can set this logger in your service provider or directly in your class using the `Log` facade:

```php
use Illuminate\Support\Facades\Log;

// In your AppServiceProvider or wherever you set up the Filter class
$filter->setLogger(Log::channel('filter'));
```

#### Enabling and Disabling Logging

- **Enable Logging**: To start logging, ensure that logging is enabled. This is typically done during setup or dynamically based on application context (e.g., only logging in a development environment).

```php
// AppServiceProvider.php

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot(): void
{
    // Enable logging globally through methods...
    Filter::enableLogging();
}
```

- **Disable Logging**: If you need to turn off logging temporarily (e.g., in a production environment to improve performance), you can disable it:

```php
// AppServiceProvider.php

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot(): void
{
    // Disable logging globally through methods...
    Filter::disableLogging();
}
```

#### Logging Actions

- **Automatic Logging**: Once the logger is set and enabled, the `Filter` class will automatically log relevant actions based on the methods being called and the filters being applied. This includes logging at various points such as when filters are added, when queries are executed, and when cache hits or misses occur.

- **Custom Logging**: You can add custom logging within the filters you define or by extending the `Filter` class. This can be useful for logging specific conditions or additional data that the default logging does not cover.

```php
public function customFilter($value) {
    if (self::shouldLog()) {
        $this->getLogger()->info("Applying custom filter with value: {$value}");
    }
    // Filter logic here
}
```

#### Checking If Logging Is Enabled

- **Conditional Logging**: Before logging any custom messages, check if logging is enabled to avoid unnecessary processing or logging errors.

```php
if (Filter::shouldLog()) {
    $this->getLogger()->info('Performing an important action');
}
```

## Testing

Testing your filters can be done using PHPUnit. Here’s an example test that ensures a `status` filter is applied correctly:

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
}
```

Ensure you have the necessary testing environment set up, including any required migrations or factory definitions.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repository and create a pull request. You can also simply open an issue with the tag "enhancement".

Don't forget to give the project a star! Thanks again!

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
