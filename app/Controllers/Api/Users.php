<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Services\UserService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * Users Resource Controller — thin HTTP layer over UserService.
 *
 * Permissions are enforced by route filters (see Config/Routes.php).
 */
class Users extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?UserService $users = null,
    ) {
        $this->users ??= Services::userService();
    }

    public function index()
    {
        return $this->apiTry(function () {
            $perPage = (int) ($this->request->getGet('perPage') ?: 10);
            $search  = $this->request->getGet('search');
            $result  = $this->users->list($perPage, $search);

            return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
        });
    }

    public function show($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $user = $this->users->findOrFail($id);

            return $this->respond(ApiResponse::success($user));
        });
    }

    public function create()
    {
        return $this->apiTry(function () {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $user = $this->users->create($data);

            return $this->respondCreated(ApiResponse::success($user, 'User created', 201));
        });
    }

    public function update($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $user = $this->users->update($id, $data);

            return $this->respond(ApiResponse::success($user, 'User updated'));
        });
    }

    public function delete($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $this->users->delete($id);

            return $this->respondDeleted(ApiResponse::success(['id' => (int) $id], 'User deleted'));
        });
    }
}
