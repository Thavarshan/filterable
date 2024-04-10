<?php

namespace Filterable\Tests;

/**
 * Class MakeFilterCommandTest.
 *
 * @covers \Filterable\Console\MakeFilterCommand
 */
final class MakeFilterCommandTest extends TestCase
{
    public function testItCanGenerateANewFilterClass(): void
    {
        $this->artisan('make:filter', ['name' => 'FooFilter'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Filters/FooFilter.php'));
    }
}
