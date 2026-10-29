<?php

namespace Filterable\Tests\Integration;

use Carbon\CarbonImmutable;
use Filterable\Attributes\AsFilter;
use Filterable\Attributes\CachePolicy;
use Filterable\Attributes\FilterFeatures;
use Filterable\Attributes\FilterRules;
use Filterable\Attributes\TransformWith;
use Filterable\Contracts\RuleFactory;
use Filterable\Filter;
use Filterable\Tests\Fixtures\MockFilterable;
use Filterable\Tests\TestCase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class DefinitionValidationTest extends TestCase
{
    /** @return array<string, array{class-string}> */
    public static function invalidHandlers(): array
    {
        return [
            'private' => [PrivateHandlerFilter::class],
            'static' => [StaticHandlerFilter::class],
            'two required values' => [TwoArgumentsFilter::class],
            'by reference' => [ReferenceHandlerFilter::class],
            'variadic' => [VariadicHandlerFilter::class],
            'empty alias' => [EmptyAliasFilter::class],
        ];
    }

    /** @param class-string $class */
    #[DataProvider('invalidHandlers')]
    public function test_invalid_handlers_fail_at_definition_resolution(string $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        new $class(new Request);
    }

    public function test_class_transformer_and_runtime_rule_factory_with_real_request(): void
    {
        $request = new Request(['date' => '2026-10-29']);
        $filter = new DateAttributeFilter($request);
        $query = $filter->apply(MockFilterable::query());
        $this->assertSame(['2026-10-29'], $query->getBindings());
        $this->assertInstanceOf(CarbonImmutable::class, $filter->getFilterables()['date']);
        $this->assertSame('2026-10-29', $filter->receivedDate);
        $this->assertSame([], $query->get()->all());
    }

    public function test_runtime_transformer_and_rules_override_attributes(): void
    {
        $filter = new DateAttributeFilter(new Request(['date' => 'anything']));
        $filter->setValidationRules(['date' => ['string']]);
        $filter->registerTransformer('date', fn () => CarbonImmutable::parse('2026-01-01'));
        $this->assertSame(['2026-01-01'], $filter->apply(MockFilterable::query())->getBindings());
    }

    public function test_unrelated_attributes_are_never_instantiated(): void
    {
        $filter = new class(new Request(['status' => 'active'])) extends Filter
        {
            #[AsFilter('status'), UnrelatedAttribute]
            protected function status(string $value): void
            {
                $this->resolveBuilder()->where('status', $value);
            }
        };
        $this->assertSame(['active'], $filter->apply(MockFilterable::query())->getBindings());
    }

    public function test_concrete_cache_policy_overrides_inherited_disable(): void
    {
        $filter = new EnabledChildFilter(new Request);
        $this->assertTrue($filter->hasFeature('caching'));
        $this->assertSame(3, $filter->getCacheExpiration());
        $filter->disableFeature('caching');
        $this->assertFalse($filter->hasFeature('caching'));
    }
}

class PrivateHandlerFilter extends Filter
{
    public function callPrivate(string $value): void
    {
        $this->value($value);
    }

    #[AsFilter('value')]
    private function value(string $value): void {}
}
class StaticHandlerFilter extends Filter
{
    #[AsFilter('value')]
    protected static function value(string $value): void {}
}
class TwoArgumentsFilter extends Filter
{
    #[AsFilter('value')]
    protected function value(string $value, string $other): void {}
}
class ReferenceHandlerFilter extends Filter
{
    #[AsFilter('value')]
    protected function value(string &$value): void {}
}
class VariadicHandlerFilter extends Filter
{
    #[AsFilter('value')]
    protected function value(string ...$value): void {}
}
class EmptyAliasFilter extends Filter
{
    #[AsFilter('')]
    protected function value(string $value): void {}
}

class DateTransformer
{
    public function __invoke(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value);
    }
}

class DateRuleFactory implements RuleFactory
{
    /** @return list<string> */
    public function __invoke(Request $request): array
    {
        return ['date', 'date_format:Y-m-d'];
    }
}

class DateAttributeFilter extends Filter
{
    public ?string $receivedDate = null;

    #[AsFilter('date'), FilterRules(factory: DateRuleFactory::class), TransformWith(DateTransformer::class)]
    protected function date(CarbonImmutable $value): void
    {
        $this->receivedDate = $value->format('Y-m-d');
        $this->resolveBuilder()->whereDate('created_at', $this->receivedDate);
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class UnrelatedAttribute
{
    public function __construct()
    {
        throw new \LogicException('Do not instantiate unrelated metadata.');
    }
}

#[FilterFeatures(['caching' => false])]
class DisabledParentFilter extends Filter {}

#[CachePolicy(ttlMinutes: 3)]
class EnabledChildFilter extends DisabledParentFilter {}
