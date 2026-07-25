<?php

namespace App\Services;

/**
 * Request-scoped organization context for multi-org data isolation.
 *
 * Populated by ApiAuth + OrgScope filters. Services must use this
 * when reading/writing organization-scoped domain data.
 */
class OrgContext
{
    private ?int $userId = null;

    private ?int $activeOrgId = null;

    /** @var list<int> */
    private array $allowedOrgIds = [];

    /** @var list<int> Child Lapas/Rutan IDs when active org is a Kanwil */
    private array $scopedOrgIds = [];

    /** @var list<string> Permission keys for the active org */
    private array $permissions = [];

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function setUser(?array $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    public function setActiveOrgId(?int $orgId): self
    {
        $this->activeOrgId = $orgId;

        return $this;
    }

    public function getActiveOrgId(): ?int
    {
        return $this->activeOrgId;
    }

    /**
     * @param list<int> $orgIds
     */
    public function setAllowedOrgIds(array $orgIds): self
    {
        $this->allowedOrgIds = array_values(array_map('intval', $orgIds));

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getAllowedOrgIds(): array
    {
        return $this->allowedOrgIds;
    }

    public function canAccessOrg(int $orgId): bool
    {
        return in_array($orgId, $this->allowedOrgIds, true);
    }

    /**
     * Org IDs to apply in WHERE IN for list/read queries.
     * Kanwil expands to child units; Lapas/Rutan is itself only.
     *
     * @param list<int> $orgIds
     */
    public function setScopedOrgIds(array $orgIds): self
    {
        $this->scopedOrgIds = array_values(array_map('intval', $orgIds));

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getScopedOrgIds(): array
    {
        if ($this->scopedOrgIds !== []) {
            return $this->scopedOrgIds;
        }

        return $this->activeOrgId !== null ? [$this->activeOrgId] : [];
    }

    /**
     * @param list<string> $permissions
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = array_values($permissions);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function hasPermission(string $permission): bool
    {
        if (in_array('*', $this->permissions, true)) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    public function reset(): self
    {
        $this->userId         = null;
        $this->activeOrgId    = null;
        $this->allowedOrgIds  = [];
        $this->scopedOrgIds   = [];
        $this->permissions    = [];
        $this->user           = null;

        return $this;
    }
}
