<?php

namespace Filterable\Tests\Integration;

use Filterable\Providers\FilterableServiceProvider;
use Filterable\Tests\TestCase;

class PublishesConfigTest extends TestCase
{
    public function test_config_file_is_registered_for_publishing(): void
    {
        $publishGroups = FilterableServiceProvider::pathsToPublish(
            FilterableServiceProvider::class,
            'filterable-config'
        );

        $this->assertNotEmpty($publishGroups, 'Config should be registered for publishing');

        // The actual source path key will depend on how the package resolves it
        $actualKeys = array_keys($publishGroups);
        $this->assertCount(1, $actualKeys, 'Should have exactly one config file to publish');

        $sourceConfigPath = $actualKeys[0];
        $this->assertFileExists($sourceConfigPath, 'Source config file should exist');
        $this->assertStringEndsWith('filterable.php', $sourceConfigPath, 'Source should be the filterable config');

        $configPath = base_path('config/filterable.php');
        $this->assertEquals(
            $configPath,
            $publishGroups[$sourceConfigPath],
            'Config should publish to the correct destination'
        );
    }

    public function test_config_file_exists(): void
    {
        $configPath = __DIR__.'/../../config/filterable.php';

        $this->assertFileExists($configPath, 'The filterable config file should exist');
    }

    public function test_config_is_valid_php(): void
    {
        $configPath = __DIR__.'/../../config/filterable.php';
        $config = include $configPath;

        $this->assertIsArray($config, 'Config file should return an array');
        $this->assertArrayHasKey('defaults', $config, 'Config should have defaults key');
    }
}
