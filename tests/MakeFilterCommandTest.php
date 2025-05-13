<?php

namespace Filterable\Tests;

class MakeFilterCommandTest extends TestCase
{
    public function test_can_generate_a_new_filter_class(): void
    {
        $this->artisan('make:filter', ['name' => 'FooFilter'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Filters/FooFilter.php'));
    }
}
