<?php

namespace App\Modules\Visit\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Visit\Services\VisitService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * Thin-module reference controller — HTTP only.
 *
 * @see \App\Modules\Visit\Services\VisitService
 */
class Visits extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?VisitService $visits = null,
    ) {
        $this->visits ??= Services::visitService();
    }

    public function index()
    {
        return $this->apiTry(function () {
            $perPage = (int) ($this->request->getGet('perPage') ?: 10);
            $search  = $this->request->getGet('search');
            $result  = $this->visits->list($perPage, $search);

            return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
        });
    }

    public function show($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $visit = $this->visits->findOrFail($id);

            return $this->respond(ApiResponse::success($visit));
        });
    }

    public function create()
    {
        return $this->apiTry(function () {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $visit = $this->visits->create($data);

            return $this->respondCreated(ApiResponse::success($visit, 'Visit created', 201));
        });
    }

    public function update($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $visit = $this->visits->update($id, $data);

            return $this->respond(ApiResponse::success($visit, 'Visit updated'));
        });
    }

    public function delete($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $this->visits->delete($id);

            return $this->respondDeleted(ApiResponse::success(['id' => (int) $id], 'Visit deleted'));
        });
    }
}
