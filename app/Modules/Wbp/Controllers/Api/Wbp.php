<?php

namespace App\Modules\Wbp\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Wbp\Services\WbpService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * Inmates Resource Controller (WBP / narapidana).
 *
 * Thin HTTP layer — business rules live in WbpService.
 */
class Wbp extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?WbpService $service = null,
    ) {
        // Prefer Config\Services over service() so static analysis / LSP can resolve the call.
        $this->service ??= Services::wbpService();
    }

    public function index()
    {
        return $this->apiTry(function () {
            $perPage = (int) ($this->request->getGet('perPage') ?: 10);
            $search  = $this->request->getGet('search');

            $result = $this->service->list($perPage, $search);

            return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
        });
    }

    public function show($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $inmate = $this->service->findOrFail($id);

            return $this->respond(ApiResponse::success($inmate));
        });
    }

    public function create()
    {
        return $this->apiTry(function () {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $inmate = $this->service->create($data);

            return $this->respondCreated(ApiResponse::success($inmate, 'Inmate created', 201));
        });
    }

    public function update($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $inmate = $this->service->update($id, $data);

            return $this->respond(ApiResponse::success($inmate, 'Inmate updated'));
        });
    }

    public function delete($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $this->service->delete($id);

            return $this->respondDeleted(ApiResponse::success(['id' => (int) $id], 'Inmate deleted'));
        });
    }
}
