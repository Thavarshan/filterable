<?php

require __DIR__.'/../vendor/autoload.php';

use Filterable\Attributes\AsFilter;
use Filterable\Definitions\DefinitionRegistry;
use Filterable\Filter;

class BenchmarkFilter extends Filter
{
    #[AsFilter('name')]
    protected function name(string $value): void {}
}

$iterations = 10000;
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    DefinitionRegistry::clear();
    DefinitionRegistry::forClass(BenchmarkFilter::class);
}
$cold = (hrtime(true) - $start) / 1e6;
$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    DefinitionRegistry::forClass(BenchmarkFilter::class);
}
$warm = (hrtime(true) - $start) / 1e6;
printf("%d definitions: cold %.2f ms; cached %.2f ms\n", $iterations, $cold, $warm);
