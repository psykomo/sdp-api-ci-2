<?php

namespace App\Modules\Inmate\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Modules\Inmate\Services\InmateService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\RESTful\ResourceController;

/**
 * Inmates Resource Controller (WBP / narapidana).
 *
 * Thin HTTP layer — business rules live in InmateService.
 */
class Inmates extends ResourceController
{
    protected $format = 'json';

    public function __construct(
        protected InmateService $service = new InmateService(),
    ) {
    }

    public function index()
    {
        $perPage = (int) ($this->request->getGet('perPage') ?: 10);
        $search  = $this->request->getGet('search');

        $result = $this->service->list($perPage, $search);

        return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
    }

    public function show($id = null)
    {
        try {
            $inmate = $this->service->findOrFail($id);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        return $this->respond(ApiResponse::success($inmate));
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (empty($data)) {
            return $this->respond(ApiResponse::error('No data provided.', 422), 422);
        }

        $inmate = $this->service->create($data);

        if ($inmate === false) {
            return $this->respond(
                ApiResponse::error('Validation failed.', 422, $this->service->errors()),
                422,
            );
        }

        return $this->respondCreated(ApiResponse::success($inmate, 'Inmate created', 201));
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (empty($data)) {
            return $this->respond(ApiResponse::error('No data provided.', 422), 422);
        }

        try {
            $inmate = $this->service->update($id, $data);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        if ($inmate === false) {
            return $this->respond(
                ApiResponse::error('Validation failed.', 422, $this->service->errors()),
                422,
            );
        }

        return $this->respond(ApiResponse::success($inmate, 'Inmate updated'));
    }

    public function delete($id = null)
    {
        try {
            $this->service->delete($id);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        return $this->respondDeleted(ApiResponse::success(['id' => (int) $id], 'Inmate deleted'));
    }
}
