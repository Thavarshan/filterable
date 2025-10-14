<?php

namespace Filterable\Tests\Unit;

use Filterable\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeFilterCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any filter files before each test
        $this->cleanUpFilters();
    }

    protected function tearDown(): void
    {
        // Clean up filter files after each test
        $this->cleanUpFilters();

        parent::tearDown();
    }

    public function test_can_generate_a_new_filter_class(): void
    {
        $this->artisan('make:filter', ['name' => 'FooFilter'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Filters/FooFilter.php'));

        // Verify basic content
        $content = File::get(app_path('Filters/FooFilter.php'));
        $this->assertStringContainsString('namespace App\Filters;', $content);
        $this->assertStringContainsString('class FooFilter extends Filter', $content);
    }

    public function test_can_generate_a_basic_filter_class(): void
    {
        $this->artisan('make:filter', [
            'name' => 'BasicFilter',
            '--basic' => true,
        ])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Filters/BasicFilter.php'));

        // Check that it contains the basic template content
        $content = File::get(app_path('Filters/BasicFilter.php'));
        $this->assertStringContainsString('namespace App\Filters;', $content);
        $this->assertStringContainsString('class BasicFilter extends Filter', $content);
    }

    public function test_can_generate_a_model_specific_filter_class(): void
    {
        $this->artisan('make:filter', [
            'name' => 'UserFilter',
            '--model' => 'User',
        ])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Filters/UserFilter.php'));

        // Check that it contains model-specific content
        $content = File::get(app_path('Filters/UserFilter.php'));
        $this->assertStringContainsString('namespace App\Filters;', $content);
        $this->assertStringContainsString('use App\Models\User;', $content);
        $this->assertStringContainsString('class UserFilter extends Filter', $content);
    }

    public function test_can_override_existing_filter_with_force_option(): void
    {
        // Create a filter first
        $this->artisan('make:filter', ['name' => 'CustomFilter'])
            ->assertExitCode(0);

        // Initial content
        $initialContent = File::get(app_path('Filters/CustomFilter.php'));

        // Modify the file to verify it changes
        File::put(
            app_path('Filters/CustomFilter.php'),
            str_replace('class CustomFilter', '// MODIFIED CLASS
class CustomFilter', $initialContent)
        );

        // Regenerate with --force
        $this->artisan('make:filter', [
            'name' => 'CustomFilter',
            '--force' => true,
        ])
            ->assertExitCode(0);

        // Check that it was indeed overwritten
        $newContent = File::get(app_path('Filters/CustomFilter.php'));
        $this->assertStringNotContainsString('// MODIFIED CLASS', $newContent);
    }

    public function test_wont_override_existing_filter_without_force_option(): void
    {
        // Create a filter first
        $this->artisan('make:filter', ['name' => 'CustomFilter'])
            ->assertExitCode(0);

        // Modify the file to verify it doesn't change
        File::put(
            app_path('Filters/CustomFilter.php'),
            File::get(app_path('Filters/CustomFilter.php')).'// MARKER COMMENT'
        );

        // Try to regenerate without --force
        $this->artisan('make:filter', ['name' => 'CustomFilter'])
            ->assertExitCode(0); // Laravel's GeneratorCommand returns 0 even for skips

        // Check that the original file is unchanged
        $content = File::get(app_path('Filters/CustomFilter.php'));
        $this->assertStringContainsString('// MARKER COMMENT', $content);
    }

    private function cleanUpFilters(): void
    {
        // Delete test filter files if they exist
        $filtersToClean = [
            'FooFilter',
            'BasicFilter',
            'UserFilter',
            'CustomFilter',
        ];

        foreach ($filtersToClean as $filter) {
            if (File::exists(app_path("Filters/{$filter}.php"))) {
                File::delete(app_path("Filters/{$filter}.php"));
            }
        }

        // Ensure the Filters directory exists
        if (! File::exists(app_path('Filters'))) {
            File::makeDirectory(app_path('Filters'), 0755, true);
        }
    }
}
