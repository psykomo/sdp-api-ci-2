<?php

namespace App\Modules\Transfer\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Modules\Transfer\Services\TransferService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\RESTful\ResourceController;
use DomainException;

class Transfers extends ResourceController
{
    protected $format = 'json';

    public function __construct(
        protected TransferService $transfers = new TransferService(),
    ) {
    }

    /**
     * POST /api/v1/inmates/{id}/transfers
     */
    public function create($inmateId = null)
    {
        $data = $this->request->getJSON(true) ?? [];

        try {
            $result = $this->transfers->execute((int) $inmateId, $data);
        } catch (PageNotFoundException $exception) {
            return $this->respond(
                ApiResponse::error($exception->getMessage(), 404),
                404,
            );
        } catch (DomainException $exception) {
            return $this->respond(
                ApiResponse::error($exception->getMessage(), 422),
                422,
            );
        }

        return $this->respondCreated(
            ApiResponse::success($result, 'Inmate transferred', 201),
        );
    }
}
