<?php

namespace Filterable\Tests\Unit;

use Filterable\Tests\Fixtures\TestFilter;
use Filterable\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery as m;
use ReflectionMethod;
use ReflectionProperty;

class TransformsFilterValuesTest extends TestCase
{
    protected $request;

    protected $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request;
        $this->filter = new TestFilter($this->request);
        $this->filter->enableFeature('valueTransformation');
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_can_register_transformer(): void
    {
        // Register a transformer
        $transformer = function ($value) {
            return strtoupper($value);
        };

        $result = $this->filter->registerTransformer('name', $transformer);

        // Check fluent interface
        $this->assertSame($this->filter, $result);

        // Verify transformer was registered
        $transformersProperty = new ReflectionProperty($this->filter, 'transformers');
        $transformersProperty->setAccessible(true);
        $transformers = $transformersProperty->getValue($this->filter);

        $this->assertArrayHasKey('name', $transformers);
        $this->assertInstanceOf(\Closure::class, $transformers['name']);
    }

    public function test_can_register_multiple_transformers(): void
    {
        // Register multiple transformers
        $this->filter->registerTransformer('name', function ($v) {
            return strtoupper($v);
        });
        $this->filter->registerTransformer('email', function ($v) {
            return strtolower($v);
        });
        $this->filter->registerTransformer('age', function ($v) {
            return (int) $v;
        });

        // Verify transformers were registered
        $transformersProperty = new ReflectionProperty($this->filter, 'transformers');
        $transformersProperty->setAccessible(true);
        $transformers = $transformersProperty->getValue($this->filter);

        $this->assertCount(3, $transformers);
        $this->assertArrayHasKey('name', $transformers);
        $this->assertArrayHasKey('email', $transformers);
        $this->assertArrayHasKey('age', $transformers);
    }

    public function test_transforms_filter_value_with_registered_transformer(): void
    {
        // Register a transformer
        $this->filter->registerTransformer('name', function ($value) {
            return strtoupper($value);
        });

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->filter, 'transformFilterValue');
        $method->setAccessible(true);

        // Transform a value
        $result = $method->invoke($this->filter, 'name', 'john');

        // Verify transformation
        $this->assertEquals('JOHN', $result);
    }

    public function test_returns_original_value_when_no_transformer_registered(): void
    {
        // Do not register any transformers

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->filter, 'transformFilterValue');
        $method->setAccessible(true);

        // Try to transform a value with no transformer
        $result = $method->invoke($this->filter, 'name', 'john');

        // Verify the original value is returned
        $this->assertEquals('john', $result);
    }

    public function test_transforms_array_values(): void
    {
        // Define a transformer
        $transformer = function ($value) {
            return strtoupper($value);
        };

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->filter, 'transformArray');
        $method->setAccessible(true);

        // Transform an array
        $result = $method->invoke($this->filter, ['john', 'jane', 'doe'], $transformer);

        // Verify transformation
        $this->assertEquals(['JOHN', 'JANE', 'DOE'], $result);
    }

    public function test_transforms_array_with_key_preservation(): void
    {
        // Define a transformer
        $transformer = function ($value) {
            return strtoupper($value);
        };

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->filter, 'transformArray');
        $method->setAccessible(true);

        // Transform an associative array
        $input = ['first' => 'john', 'last' => 'doe', 'middle' => 'smith'];
        $result = $method->invoke($this->filter, $input, $transformer);

        // Verify transformation preserves keys
        $this->assertEquals(['first' => 'JOHN', 'last' => 'DOE', 'middle' => 'SMITH'], $result);
    }

    public function test_transforms_values_during_filter_application(): void
    {
        // Create a filter that exposes the transformation process
        $filter = new class($this->request) extends TestFilter
        {
            public $transformedValues = [];

            protected function transformFilterValues(): void
            {
                $filterables = $this->getFilterables();

                foreach ($filterables as $filter => $value) {
                    if (isset($this->transformers[$filter])) {
                        $this->transformedValues[$filter] = $this->transformFilterValue($filter, $value);
                        $this->filterables[$filter] = $this->transformedValues[$filter];
                    }
                }
            }
        };

        // Enable value transformation
        $filter->enableFeature('valueTransformation');

        // Register a transformer
        $filter->registerTransformer('name', function ($value) {
            return strtoupper($value);
        });

        // Add a filterable value
        $filter->appendFilterable('name', 'john');

        // Create a mock builder
        $builder = m::mock(\Illuminate\Database\Eloquent\Builder::class);
        $builder->shouldReceive('get')->andReturn(collect());

        // Apply the filter
        $filter->apply($builder);

        // Verify the value was transformed
        $this->assertEquals(['name' => 'JOHN'], $filter->transformedValues);
    }

    public function test_transformers_handle_complex_data_types(): void
    {
        // Register a transformer for arrays
        $this->filter->registerTransformer('tags', function ($values) {
            if (! is_array($values)) {
                return [$values];
            }

            return array_map('strtoupper', $values);
        });

        // Register a transformer for objects
        $this->filter->registerTransformer('user', function ($value) {
            if (is_object($value) && isset($value->name)) {
                $value->name = strtoupper($value->name);
            }

            return $value;
        });

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->filter, 'transformFilterValue');
        $method->setAccessible(true);

        // Transform an array
        $tagsResult = $method->invoke($this->filter, 'tags', ['php', 'laravel', 'testing']);

        // Transform a string to array
        $singleTagResult = $method->invoke($this->filter, 'tags', 'php');

        // Transform an object
        $user = new \stdClass;
        $user->name = 'john';
        $user->email = 'john@example.com';
        $userResult = $method->invoke($this->filter, 'user', $user);

        // Verify transformations
        $this->assertEquals(['PHP', 'LARAVEL', 'TESTING'], $tagsResult);
        $this->assertEquals(['php'], $singleTagResult);
        $this->assertEquals('JOHN', $userResult->name);
        $this->assertEquals('john@example.com', $userResult->email);
    }

    public function test_transformers_can_change_value_type(): void
    {
        // Register transformers that change value types
        $this->filter->registerTransformer('string_to_int', function ($value) {
            return (int) $value;
        });

        $this->filter->registerTransformer('int_to_bool', function ($value) {
            return (bool) $value;
        });

        $this->filter->registerTransformer('string_to_array', function ($value) {
            return explode(',', $value);
        });

        // Use reflection to call the protected method
        $method = new ReflectionMethod($this->filter, 'transformFilterValue');
        $method->setAccessible(true);

        // Transform values
        $intResult = $method->invoke($this->filter, 'string_to_int', '123');
        $boolResult = $method->invoke($this->filter, 'int_to_bool', 0);
        $arrayResult = $method->invoke($this->filter, 'string_to_array', 'a,b,c');

        // Verify transformations
        $this->assertIsInt($intResult);
        $this->assertEquals(123, $intResult);

        $this->assertIsBool($boolResult);
        $this->assertFalse($boolResult);

        $this->assertIsArray($arrayResult);
        $this->assertEquals(['a', 'b', 'c'], $arrayResult);
    }

    public function test_transformers_not_applied_when_feature_disabled(): void
    {
        // Create a filter that exposes the transformation process
        $filter = new class($this->request) extends TestFilter
        {
            public $transformCalled = false;

            protected function transformFilterValues(): void
            {
                $this->transformCalled = true;
                parent::transformFilterValues();
            }
        };

        // Disable value transformation
        $filter->disableFeature('valueTransformation');

        // Register a transformer
        $filter->registerTransformer('name', function ($value) {
            return strtoupper($value);
        });

        // Add a filterable value
        $filter->appendFilterable('name', 'john');

        // Create a mock builder
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('get')->andReturn(collect());

        // Apply the filter
        $filter->apply($builder);

        // Verify the transform method was not called
        $this->assertFalse($filter->transformCalled);
    }
}
