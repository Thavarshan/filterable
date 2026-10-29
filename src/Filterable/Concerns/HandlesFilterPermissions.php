<?php

namespace Filterable\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

trait HandlesFilterPermissions
{
    protected ?Authenticatable $authorizationActor = null;

    protected bool $actorSet = false;

    /**
     * Filters that require specific permissions.
     *
     * @var array<string, string|list<string>>
     */
    protected array $filterPermissions = [];

    public function authorizeFor(?Authenticatable $actor): self
    {
        $this->authorizationActor = $actor;
        $this->actorSet = true;

        return $this;
    }

    /**
     * Set permission requirements for filters.
     *
     * @param  array<string, string|list<string>>  $permissions
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
                $gate = app(Gate::class)->forUser($actor);
                $allowed = $actor !== null && ($policy->any ? $gate->any((array) $permission) : $gate->check((array) $permission));
            } else {
                $allowed = $actor !== null && $this->userHasPermission($permission);
            }

            if (! $allowed) {
                if ($policy?->reject) {
                    throw new AuthorizationException('Filter permission denied.');
                }
                // Remove the filter from filterables
                unset($this->filterables[$filter]);

            }
        }
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param  string|list<string>  $permission
     */
    protected function userHasPermission(string|array $permission): bool
    {
        // Override this method in your specific filter class
        // to implement your authorization logic
        $actor = $this->actorSet ? $this->authorizationActor : ($this->request->user() ?? $this->forUser);

        return $actor !== null && app(Gate::class)->forUser($actor)->check((array) $permission);
    }
}
