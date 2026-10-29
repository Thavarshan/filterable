<?php

namespace Filterable\Concerns;

trait HandlesFilterPermissions
{
    protected ?\Illuminate\Contracts\Auth\Authenticatable $authorizationActor = null;

    protected bool $actorSet = false;

    public function authorizeFor(?\Illuminate\Contracts\Auth\Authenticatable $actor): self
    {
        $this->authorizationActor = $actor;
        $this->actorSet = true;

        return $this;
    }
    /**
     * Filters that require specific permissions.
     *
     * @var array<string, string|array>
     */
    protected array $filterPermissions = [];

    /**
     * Set permission requirements for filters.
     */
    public function setFilterPermissions(array $permissions): self
    {
        $this->filterPermissions = $permissions;

        return $this;
    }

    /**
     * Check if the user has permission to use all requested filters.
     */
    protected function checkFilterPermissions(): void
    {
        if (empty($this->filterPermissions)) {
            return;
        }

        $requestedFilters = array_keys($this->getFilterables());
        $restrictedFilters = array_keys($this->filterPermissions);

        $filtersToCheck = array_intersect($requestedFilters, $restrictedFilters);

        foreach ($filtersToCheck as $filter) {
            $permission = $this->filterPermissions[$filter];

            // Skip filters where the user doesn't have permission
            $policy = $this->permissionPolicies[$filter] ?? null;
            $actor = $this->actorSet ? $this->authorizationActor : ($this->request->user() ?? $this->forUser);
            if ($policy !== null) {
                $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class)->forUser($actor);
                $allowed = $actor !== null && ($policy->any ? $gate->any((array) $permission) : $gate->check((array) $permission));
            } else {
                $allowed = $actor !== null && $this->userHasPermission($permission);
            }

            if (! $allowed) {
                if ($policy?->reject) {
                    throw new \Illuminate\Auth\Access\AuthorizationException('Filter permission denied.');
                }
                // Remove the filter from filterables
                unset($this->filterables[$filter]);

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
        $actor = $this->actorSet ? $this->authorizationActor : ($this->request->user() ?? $this->forUser);

        return $actor !== null && app(\Illuminate\Contracts\Auth\Access\Gate::class)->forUser($actor)->check((array) $permission);
    }
}
