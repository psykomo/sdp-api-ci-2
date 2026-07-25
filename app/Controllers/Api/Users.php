<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Services\UserService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\RESTful\ResourceController;

/**
 * Users Resource Controller
 *
 * Thin HTTP layer — business logic lives in UserService.
 *
 * Routes:
 *   GET    /api/v1/users         → index()
 *   POST   /api/v1/users         → create()
 *   GET    /api/v1/users/{id}    → show()
 *   PUT    /api/v1/users/{id}    → update()
 *   PATCH  /api/v1/users/{id}    → update()
 *   DELETE /api/v1/users/{id}    → delete()
 */
class Users extends ResourceController
{
    protected $format = 'json';

    public function __construct(
        protected UserService $users = new UserService(),
    ) {
    }

    public function index()
    {
        if (! service('orgContext')->hasPermission('user.read')) {
            return $this->respond(ApiResponse::error('Forbidden: missing permission user.read', 403), 403);
        }

        $perPage = (int) ($this->request->getGet('perPage') ?: 10);
        $search  = $this->request->getGet('search');
        $result  = $this->users->list($perPage, $search);

        return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
    }

    public function show($id = null)
    {
        if (! service('orgContext')->hasPermission('user.read')) {
            return $this->respond(ApiResponse::error('Forbidden: missing permission user.read', 403), 403);
        }

        try {
            $user = $this->users->findOrFail($id);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        return $this->respond(ApiResponse::success($user));
    }

    public function create()
    {
        if (! service('orgContext')->hasPermission('user.write')) {
            return $this->respond(ApiResponse::error('Forbidden: missing permission user.write', 403), 403);
        }

        $data = $this->request->getJSON(true);

        if (empty($data)) {
            return $this->respond(ApiResponse::error('No data provided.', 422), 422);
        }

        if (! empty($data['password'])) {
            $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        $user = $this->users->create($data);

        if ($user === false) {
            return $this->respond(
                ApiResponse::error('Validation failed.', 422, $this->users->errors()),
                422,
            );
        }

        return $this->respondCreated(ApiResponse::success($user, 'User created', 201));
    }

    public function update($id = null)
    {
        if (! service('orgContext')->hasPermission('user.write')) {
            return $this->respond(ApiResponse::error('Forbidden: missing permission user.write', 403), 403);
        }

        $data = $this->request->getJSON(true);

        if (empty($data)) {
            return $this->respond(ApiResponse::error('No data provided.', 422), 422);
        }

        if (! empty($data['password'])) {
            $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        try {
            $user = $this->users->update($id, $data);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        if ($user === false) {
            return $this->respond(
                ApiResponse::error('Validation failed.', 422, $this->users->errors()),
                422,
            );
        }

        return $this->respond(ApiResponse::success($user, 'User updated'));
    }

    public function delete($id = null)
    {
        if (! service('orgContext')->hasPermission('user.delete')) {
            return $this->respond(ApiResponse::error('Forbidden: missing permission user.delete', 403), 403);
        }

        try {
            $this->users->delete($id);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        return $this->respondDeleted(ApiResponse::success(['id' => (int) $id], 'User deleted'));
    }
}
