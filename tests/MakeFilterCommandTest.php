<?php

namespace Filterable\Tests;

/**
 * Class MakeFilterCommandTest.
 *
 * @covers \Filterable\Console\MakeFilterCommand
 */
final class MakeFilterCommandTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_generate_a_new_filter_class(): void
    {
        $this->artisan('make:filter', ['name' => 'FooFilter'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Filters/FooFilter.php'));
    }
}
