<?php

namespace App\Modules\Mutasi\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Mutasi\Services\MutasiGolonganService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * M1 — mutasi golongan API.
 */
class MutasiGolongan extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?MutasiGolonganService $service = null,
    ) {
        $this->service ??= Services::mutasiGolonganService();
    }

    /**
     * GET /api/v1/mutasi/golongan?id_perkara=…
     * List mutasi for a perkara.
     */
    public function index()
    {
        return $this->apiTry(function () {
            $idPerkara = (string) ($this->request->getGet('id_perkara') ?? '');
            if ($idPerkara === '') {
                return $this->respond(ApiResponse::error('Query id_perkara is required.', 422), 422);
            }

            $perPage = (int) ($this->request->getGet('perPage') ?: 50);
            $page    = (int) ($this->request->getGet('page') ?: 1);
            $result  = $this->service->listForPerkara($idPerkara, $perPage, $page);

            return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
        });
    }

    /**
     * GET /api/v1/mutasi/golongan/options?id_perkara=…
     */
    public function options()
    {
        return $this->apiTry(function () {
            $idPerkara = (string) ($this->request->getGet('id_perkara') ?? '');
            if ($idPerkara === '') {
                return $this->respond(ApiResponse::error('Query id_perkara is required.', 422), 422);
            }

            $items = $this->service->options($idPerkara);

            return $this->respond(ApiResponse::success([
                'id_perkara' => $idPerkara,
                'items'      => $items,
            ]));
        });
    }

    /**
     * GET /api/v1/mutasi/golongan/{id_mutasi_tahanan}
     */
    public function show($id = null)
    {
        return $this->apiTry(function () use ($id) {
            $row = $this->service->findOrFail((string) $id);

            return $this->respond(ApiResponse::success($row));
        });
    }

    /**
     * POST /api/v1/mutasi/golongan
     */
    public function create()
    {
        return $this->apiTry(function () {
            $data = $this->request->getJSON(true);
            if (empty($data) || ! is_array($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $result = $this->service->create($data);

            return $this->respondCreated(ApiResponse::success($result, 'Mutasi golongan created', 201));
        });
    }
}
