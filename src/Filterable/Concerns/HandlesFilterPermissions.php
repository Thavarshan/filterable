<?php

namespace Filterable\Concerns;

trait HandlesFilterPermissions
{
    /**
     * Filters that require specific permissions.
     *
     * @var array<string, string|array>
     */
    protected array $filterPermissions = [];

    /**
     * Check if the user has permission to use all requested filters.
     */
    protected function checkFilterPermissions(): void
    {
        if (empty($this->filterPermissions) || is_null($this->forUser)) {
            return;
        }

        $requestedFilters = array_keys($this->getFilterables());
        $restrictedFilters = array_keys($this->filterPermissions);

        $filtersToCheck = array_intersect($requestedFilters, $restrictedFilters);

        foreach ($filtersToCheck as $filter) {
            $permission = $this->filterPermissions[$filter];

            // Skip filters where the user doesn't have permission
            if (! $this->userHasPermission($permission)) {
                // Remove the filter from filterables
                unset($this->filterables[$filter]);

                if (method_exists($this, 'logInfo')) {
                    $this->logInfo('Filter removed due to insufficient permissions', [
                        'filter' => $filter,
                        'required_permission' => $permission,
                    ]);
                }
            }
        }
    }

    /**
     * Check if the user has a specific permission.
     */
    protected function userHasPermission(string|array $permission): bool
    {
        // Override this method in your specific filter class
        // to implement your authorization logic
        return true;
    }

    /**
     * Set permission requirements for filters.
     */
    public function setFilterPermissions(array $permissions): self
    {
        $this->filterPermissions = $permissions;

        return $this;
    }
}
