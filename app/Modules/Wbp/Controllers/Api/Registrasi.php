<?php

namespace App\Modules\Wbp\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Wbp\Services\WbpService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * R3 create, R4 update, R6 list/show registrasi on legacy multi-table spine.
 */
class Registrasi extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?WbpService $service = null,
    ) {
        $this->service ??= Services::wbpService();
    }

    /** GET /api/v1/wbp/registrasi */
    public function index()
    {
        return $this->apiTry(function () {
            $perPage = (int) ($this->request->getGet('perPage') ?: 10);
            $page    = (int) ($this->request->getGet('page') ?: 1);
            $search  = $this->request->getGet('search');

            $result = $this->service->listRegistrasi(
                $perPage,
                $search !== null ? (string) $search : null,
                $page,
            );

            return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
        });
    }

    /** GET /api/v1/wbp/registrasi/{id_perkara} */
    public function show($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $row = $this->service->findRegistrasiOrFail((string) $id);

            return $this->respond(ApiResponse::success($row));
        });
    }

    /** POST /api/v1/wbp/registrasi */
    public function create()
    {
        return $this->apiTry(function () {
            $data = $this->request->getJSON(true);
            if (empty($data) || ! is_array($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $result = $this->service->createRegistrasi($data);

            return $this->respondCreated(ApiResponse::success($result, 'Registrasi created', 201));
        });
    }

    /** PUT/PATCH /api/v1/wbp/registrasi/{id_perkara} */
    public function update($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $data = $this->request->getJSON(true);
            if (empty($data) || ! is_array($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $result = $this->service->updateRegistrasi((string) $id, $data);

            return $this->respond(ApiResponse::success($result, 'Registrasi updated'));
        });
    }
}
