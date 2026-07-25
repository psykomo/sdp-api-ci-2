<?php

namespace App\Services;

use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Shared-core user business logic (pattern sample for modules).
 */
class UserService
{
    public function __construct(
        protected UserModel $users = new UserModel(),
    ) {
    }

    /**
     * @return array{items: list<mixed>, meta: array<string, int>}
     */
    public function list(int $perPage = 10, ?string $search = null): array
    {
        $builder = $this->users;

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

    public function findOrFail(int|string $id): object|array
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound("User with ID {$id} not found.");
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     * @return object|array|false
     */
    public function create(array $data): object|array|false
    {
        $id = $this->users->insert($data);

        if ($id === false) {
            return false;
        }

        return $this->users->find($id);
    }

    /**
     * @param array<string, mixed> $data
     * @return object|array|false
     */
    public function update(int|string $id, array $data): object|array|false
    {
        $this->findOrFail($id);

        if ($this->users->update($id, $data) === false) {
            return false;
        }

        return $this->users->find($id);
    }

    public function delete(int|string $id): void
    {
        $this->findOrFail($id);
        $this->users->delete($id);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->users->errors();
    }
}
