<?php

namespace App\Modules\Transfer\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Transfer\Services\TransferService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

class Transfers extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?TransferService $transfers = null,
    ) {
        $this->transfers ??= Services::transferService();
    }

    /**
     * POST /api/v1/inmates/{id}/transfers
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
