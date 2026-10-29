<?php

namespace Filterable\Tests\Integration;

use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class GeneratedAttributesTest extends TestCase
{
    public function test_generated_variants_load_and_execute(): void
    {
        MockFilterable::factory()->create(['name' => 'ALICE']);
        foreach (['basic', 'default', 'model'] as $variant) {
            foreach ([false, true] as $php85) {
                if ($php85 && PHP_VERSION_ID < 80500) {
                    continue;
                }
                $name = 'Generated'.ucfirst($variant).($php85 ? 'Php85' : '').'Filter';
                $arguments = ['name' => $name, '--attributes' => true, '--force' => true];
                if ($variant === 'basic') {
                    $arguments['--basic'] = true;
                } elseif ($variant === 'model') {
                    $arguments['--model'] = MockFilterable::class;
                }
                if ($php85) {
                    $arguments['--php85'] = true;
                }
                $this->assertSame(0, Artisan::call('make:filter', $arguments));
                $path = app_path('Filters/'.$name.'.php');
                try {
                    require_once $path;
                    $class = 'App\\Filters\\'.$name;
                    $request = new Request([$variant === 'model' ? 'search' : 'name' => 'ALICE']);
                    app()->instance(Request::class, $request);
                    $filter = app($class);
                    $this->assertInstanceOf(\Filterable\Filter::class, $filter);
                    $query = $filter->apply(MockFilterable::query());
                    $this->assertSame([$variant === 'model' ? '%ALICE%' : 'ALICE'], $query->getBindings());
                    $this->assertCount(1, $query->get());
                } finally {
                    unlink($path);
                }
            }
        }
    }

    public function test_php85_generator_fails_before_writing_on_older_php(): void
    {
        if (PHP_VERSION_ID >= 80500) {
            $this->markTestSkipped('Older parser guard.');
        }
        try {
            Artisan::call('make:filter', ['name' => 'UnsupportedCallableFilter', '--php85' => true]);
            $this->fail('Expected version error');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('PHP 8.5', $exception->getMessage());
            $this->assertFileDoesNotExist(app_path('Filters/UnsupportedCallableFilter.php'));
        }
    }
}
