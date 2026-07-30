<?php

namespace App\Modules\Wbp\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Wbp\Services\WbpService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * R5 — history_registrasi list/show/create/update/soft-delete under a perkara.
 */
class HistoryRegistrasi extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?WbpService $service = null,
    ) {
        $this->service ??= Services::wbpService();
    }

    /** GET /api/v1/wbp/registrasi/{id_perkara}/history */
    public function index($idPerkara = null)
    {
        return $this->apiTry(function () use ($idPerkara) {
            $perPage = (int) ($this->request->getGet('perPage') ?: 50);
            $page    = (int) ($this->request->getGet('page') ?: 1);

            $result = $this->service->listHistory((string) $idPerkara, $perPage, $page);

            return $this->respond(ApiResponse::paginated($result['items'], $result['meta']));
        });
    }

    /** GET /api/v1/wbp/registrasi/{id_perkara}/history/{id_history_reg} */
    public function show($idPerkara = null, $idHistoryReg = null)
    {
        return $this->apiTry(function () use ($idPerkara, $idHistoryReg) {
            $row = $this->service->findHistoryOrFail((string) $idPerkara, (string) $idHistoryReg);

            return $this->respond(ApiResponse::success($row));
        });
    }

    /** POST /api/v1/wbp/registrasi/{id_perkara}/history */
    public function create($idPerkara = null)
    {
        return $this->apiTry(function () use ($idPerkara) {
            $data = $this->request->getJSON(true);
            if (! is_array($data)) {
                $data = [];
            }

            $result = $this->service->createHistory((string) $idPerkara, $data);

            return $this->respondCreated(ApiResponse::success($result, 'History created', 201));
        });
    }

    /** PUT/PATCH /api/v1/wbp/registrasi/{id_perkara}/history/{id_history_reg} */
    public function update($idPerkara = null, $idHistoryReg = null)
    {
        return $this->apiTry(function () use ($idPerkara, $idHistoryReg) {
            $data = $this->request->getJSON(true);
            if (empty($data) || ! is_array($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $result = $this->service->updateHistory(
                (string) $idPerkara,
                (string) $idHistoryReg,
                $data,
            );

            return $this->respond(ApiResponse::success($result, 'History updated'));
        });
    }

    /** DELETE /api/v1/wbp/registrasi/{id_perkara}/history/{id_history_reg} */
    public function delete($idPerkara = null, $idHistoryReg = null)
    {
        return $this->apiTry(function () use ($idPerkara, $idHistoryReg) {
            $result = $this->service->deleteHistory((string) $idPerkara, (string) $idHistoryReg);

            return $this->respondDeleted(ApiResponse::success($result, 'History soft-deleted'));
        });
    }
}
