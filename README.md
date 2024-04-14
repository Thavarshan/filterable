# Filterable

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

## Customization

### Caching

In your filter class, you can control caching by using the `enableCaching` static method. Set the `$useCache` static property to `true` to enable caching, or `false` to disable it. You can also customise the duration of the cache by modifying the `$cacheExpiration` property.`

```php
// AppServiceProvider.php

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot(): void
{
    Filter::enableCaching(true); // Control caching
}
```

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
