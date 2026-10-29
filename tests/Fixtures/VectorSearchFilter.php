<?php

namespace Filterable\Tests\Fixtures;

use Filterable\Attributes\AsFilter;
use Filterable\Attributes\FilterRules;
use Filterable\Filter;
use Illuminate\Validation\Validator;

/** Optional Laravel 13 recipe: no embedding provider or AI dependency. */
class VectorSearchFilter extends Filter
{
    #[AsFilter('status'), FilterRules(['in:active,inactive'])]
    protected function status(string $value): void
    {
        $this->resolveBuilder()->where('status', $value);
    }

    /** @param array{vector: list<float>, threshold?: float|int, limit?: int} $value */
    #[AsFilter('semantic'), FilterRules([
        'required', 'array:vector,threshold,limit',
    ])]
    protected function semantic(array $value): void
    {
        // Validate the entire payload before applying any predicate.
        $this->resolveBuilder()->whereVectorSimilarTo('embedding', $value['vector'], minSimilarity: (float) ($value['threshold'] ?? 0.4))
            ->limit((int) ($value['limit'] ?? 10));
    }

    protected function validateFilterInputs(): void
    {
        parent::validateFilterInputs();
        $semantic = $this->getFilterables()['semantic'] ?? null;
        if (is_array($semantic)) {
            \Illuminate\Support\Facades\Validator::make($semantic, [
                'vector' => ['required', 'array', 'size:3'],
                'vector.*' => ['required', 'numeric', 'between:-1.0e20,1.0e20'],
                'threshold' => ['sometimes', 'numeric', 'between:0,1'],
                'limit' => ['sometimes', 'integer', 'between:1,100'],
            ])->after(function (Validator $validator) use ($semantic) {
                $vector = $semantic['vector'] ?? [];
                if (is_array($vector) && count(array_filter($vector, static fn ($v) => is_numeric($v) && (float) $v !== 0.0)) === 0) {
                    $validator->errors()->add('vector', 'Cosine similarity requires a nonzero vector.');
                }
            })->validate();
        }
    }
}
