<?php

namespace App\Modules\Mutasi\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Mutasi\Services\MutasiService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

class Mutasi extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?MutasiService $transfers = null,
    ) {
        $this->transfers ??= Services::mutasiService();
    }

    /**
     * POST /api/v1/wbp/{id}/transfers
     */
    public function create($inmateId = null)
    {
        return $this->apiTry(function () use ($inmateId) {
            $data = $this->request->getJSON(true) ?? [];

            $result = $this->transfers->execute((int) $inmateId, $data);

            return $this->respondCreated(
                ApiResponse::success($result, 'Inmate transferred', 201),
            );
        });
    }
}
