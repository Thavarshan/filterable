<?php

namespace Filterable\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait ValidatesFilterInput
{
    /**
     * Validation rules for filters.
     *
     * @var array<string, string|array>
     */
    protected array $validationRules = [];

    /**
     * Validation messages.
     *
     * @var array<string, string>
     */
    protected array $validationMessages = [];

    /**
     * Set validation rules for filter inputs.
     */
    public function setValidationRules(array $rules): self
    {
        $this->validationRules = $rules;

        return $this;
    }

    /**
     * Add a validation rule for a specific filter.
     */
    public function addValidationRule(string $filter, string|array $rule): self
    {
        $this->validationRules[$filter] = $rule;

        return $this;
    }

    /**
     * Set validation messages.
     */
    public function setValidationMessages(array $messages): self
    {
        $this->validationMessages = $messages;

        return $this;
    }

    /**
     * Validate the filter inputs before applying them.
     *
     * @throws ValidationException
     */
    protected function validateFilterInputs(): void
    {
        if (empty($this->validationRules)) {
            return;
        }

        $filterables = $this->getFilterables();

        // Only validate filters that have corresponding rules
        $toValidate = array_intersect_key($filterables, $this->validationRules);

        if (empty($toValidate)) {
            return;
        }

        if (method_exists($this, 'logInfo')) {
            $this->logInfo('Validating filter inputs', [
                'inputs' => $toValidate,
                'rules' => array_intersect_key($this->validationRules, $toValidate),
            ]);
        }

        Validator::make($toValidate, $this->validationRules, $this->validationMessages)->validate();
    }
}
