<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\UserModel;
use App\Models\UserOrganizationRoleModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use DomainException;

/**
 * Shared-core user management, scoped to the active OrgContext.
 *
 * Users are global rows; membership is via user_organization_roles.
 * List/find/update/delete only touch users assigned to scoped org IDs.
 * Create always assigns the new user to the active organization.
 */
class UserService
{
    public function __construct(
        protected UserModel $users = new UserModel(),
        protected UserOrganizationRoleModel $userOrgRoles = new UserOrganizationRoleModel(),
        protected ?OrgContext $orgContext = null,
        protected ?UnitOfWork $unitOfWork = null,
    ) {
        $this->orgContext ??= service('orgContext');
        $this->unitOfWork ??= service('unitOfWork');
    }

    /**
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function list(int $perPage = 10, ?string $search = null): array
    {
        $scoped = $this->orgContext->getScopedOrgIds();
        if ($scoped === []) {
            return [
                'items' => [],
                'meta'  => ['page' => 1, 'perPage' => $perPage, 'total' => 0, 'pageCount' => 0],
            ];
        }

        $userIds = $this->userIdsInScope($scoped);
        if ($userIds === []) {
            return [
                'items' => [],
                'meta'  => ['page' => 1, 'perPage' => $perPage, 'total' => 0, 'pageCount' => 0],
            ];
        }

        $builder = $this->users->whereIn('id', $userIds);

        if ($search) {
            $builder = $builder->groupStart()
                ->like('name', $search)
                ->orLike('email', $search)
                ->groupEnd();
        }

        $items = $builder->paginate($perPage, 'users');
        $pager = $this->users->pager;

        return [
            'items' => $items,
            'meta'  => [
                'page'      => $pager->getCurrentPage('users'),
                'perPage'   => $perPage,
                'total'     => $pager->getTotal('users'),
                'pageCount' => $pager->getPageCount('users'),
            ],
        ];
    }

    public function findOrFail(int|string $id): object
    {
        $user = $this->users->find($id);

        if ($user === null || ! $this->isInScope((int) $id)) {
            throw PageNotFoundException::forPageNotFound("User with ID {$id} not found.");
        }

        return $user;
    }

    /**
     * Create a user and assign them to the active organization.
     *
     * @param array<string, mixed> $data  May include password, optional role_id
     */
    public function create(array $data): object
    {
        $activeOrgId = $this->orgContext->getActiveOrgId();
        if ($activeOrgId === null) {
            throw new DomainException('No active organization in context.');
        }

        $roleId = isset($data['role_id']) ? (int) $data['role_id'] : 0;
        unset($data['role_id']);

        if ($roleId <= 0) {
            throw new ValidationException('role_id is required.', [
                'role_id' => 'A role in the active organization is required.',
            ]);
        }

        $data = $this->normalizePassword($data);

        if (empty($data['password_hash'])) {
            throw new ValidationException('Password is required.', [
                'password' => 'Password is required.',
            ]);
        }

        return $this->unitOfWork->run(function () use ($data, $activeOrgId, $roleId): object {
            $id = $this->users->insert($data);
            if ($id === false) {
                throw new ValidationException(
                    'Validation failed.',
                    $this->users->errors(),
                );
            }

            // Always bind the new user to the active org so org-scoped lists stay correct.
            $assignmentId = $this->userOrgRoles->insert([
                'user_id'         => (int) $id,
                'organization_id' => $activeOrgId,
                'role_id'         => $roleId,
            ]);

            if ($assignmentId === false) {
                throw new ValidationException(
                    'Unable to assign role.',
                    $this->userOrgRoles->errors(),
                );
            }

            $user = $this->users->find($id);
            if ($user === null) {
                throw new DomainException('Created user could not be reloaded.');
            }

            return $user;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): object
    {
        $this->findOrFail($id);

        unset($data['role_id']);
        $data = $this->normalizePassword($data);

        // Empty password on update means "leave unchanged".
        if (array_key_exists('password_hash', $data) && $data['password_hash'] === null) {
            unset($data['password_hash']);
        }

        if ($this->users->update($id, $data) === false) {
            throw new ValidationException('Validation failed.', $this->users->errors());
        }

        $user = $this->users->find($id);
        if ($user === null) {
            throw new DomainException('Updated user could not be reloaded.');
        }

        return $user;
    }

    public function delete(int|string $id): void
    {
        $this->findOrFail($id);
        $this->users->delete($id);
    }

    /**
     * @param list<int> $scopedOrgIds
     * @return list<int>
     */
    private function userIdsInScope(array $scopedOrgIds): array
    {
        $rows = $this->userOrgRoles
            ->select('user_id')
            ->whereIn('organization_id', $scopedOrgIds)
            ->findAll();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $rows,
        )));
    }

    private function isInScope(int $userId): bool
    {
        $scoped = $this->orgContext->getScopedOrgIds();
        if ($scoped === []) {
            return false;
        }

        $row = $this->userOrgRoles
            ->where('user_id', $userId)
            ->whereIn('organization_id', $scoped)
            ->first();

        return $row !== null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePassword(array $data): array
    {
        if (array_key_exists('password', $data)) {
            $password = (string) $data['password'];
            unset($data['password']);

            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            } else {
                $data['password_hash'] = null;
            }
        }

        return $data;
    }
}
