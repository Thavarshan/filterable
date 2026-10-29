<?php

namespace Filterable\Tests\Integration;

use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReadmeExamplesTest extends TestCase
{
    /** Load the actual guide code, so documentation drift fails the suite. */
    private function loadExample(string $declaration, string $namespace = ''): void
    {
        $readme = file_get_contents(__DIR__.'/../../README.md');
        $this->assertIsString($readme);
        preg_match_all('/```php\n(.*?)\n```/s', $readme, $blocks);
        foreach ($blocks[1] as $code) {
            if (! str_contains($code, $declaration)) {
                continue;
            }
            $code = str_replace(
                ['App\\Filters', 'App\\Models', 'App\\Http\\Controllers'],
                ['Filterable\\Tests\\Readme\\Filters', 'Filterable\\Tests\\Readme\\Models', 'Filterable\\Tests\\Readme\\Controllers'],
                $code
            );
            if (! str_starts_with($code, '<?php')) {
                $code = '<?php namespace '.$namespace.';'.PHP_EOL.$code;
            }
            eval('?>'.$code);

            return;
        }
        $this->fail('Missing runnable README declaration: '.$declaration);
    }

    public function test_quick_start_classes_and_controller_execute_the_documented_request(): void
    {
        $this->loadExample('class PostFilter extends Filter');
        $this->loadExample('class Post extends Model');
        $this->loadExample('class PostController');
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('status');
            $table->decimal('price');
            $table->boolean('is_available');
            $table->date('published_at');
        });
        DB::table('posts')->insert([
            ['id' => 1, 'title' => 'Laravel filters', 'body' => 'Guide', 'status' => 'published', 'price' => 0, 'is_available' => false, 'published_at' => '2026-01-02'],
            ['id' => 2, 'title' => 'Laravel models', 'body' => 'Guide', 'status' => 'published', 'price' => 10, 'is_available' => true, 'published_at' => '2026-01-03'],
        ]);
        app('router')->get('/readme-posts', ['Filterable\\Tests\\Readme\\Controllers\\PostController', 'index']);
        $this->getJson('/readme-posts?status=published&q=Laravel&min_price=0&available=0&published_after=2026-01-01')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', 1);

        $this->loadExample('class LegacyPostFilter extends', 'Filterable\\Tests\\Readme\\Legacy');
        $class = 'Filterable\\Tests\\Readme\\Legacy\\LegacyPostFilter';
        $filter = app()->make($class, ['request' => new Request(['status' => 'published', 'published_after' => '2026-01-02'])]);
        $this->assertInstanceOf(Filter::class, $filter);
        $modelClass = 'Filterable\\Tests\\Readme\\Models\\Post';
        $model = app($modelClass);
        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame([1, 2], $filter->apply($model->newQuery())->pluck('id')->all());
    }

    public function test_vector_example_rejects_invalid_input_before_query_mutation(): void
    {
        $this->loadExample('class SemanticPostFilter extends', 'Filterable\\Tests\\Readme\\Vector');
        $class = 'Filterable\\Tests\\Readme\\Vector\\SemanticPostFilter';
        $filter = app()->make($class, ['request' => new Request(['semantic' => ['vector' => [0, 0, 0]]])]);
        $this->assertInstanceOf(Filter::class, $filter);
        $query = MockFilterable::query();
        try {
            $filter->apply($query);
            $this->fail('README vector example accepted a zero vector');
        } catch (ValidationException) {
            $this->assertSame([], $query->getBindings());
        }
    }
}
