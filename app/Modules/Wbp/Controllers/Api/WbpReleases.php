<?php

namespace App\Modules\Wbp\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Wbp\Actions\PembebasanWbp;
use CodeIgniter\RESTful\ResourceController;

/**
 * Inmate release (pembebasan) endpoint.
 *
 * Process controllers may call an Action directly when the process is
 * HTTP-facing only. Other modules should go through WbpService if/when
 * the process is exposed on the facade.
 */
class WbpReleases extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected PembebasanWbp $releaseInmate = new PembebasanWbp(),
    ) {
    }

    /**
     * POST /api/v1/wbp/{inmateId}/releases
     */
    public function create($inmateId = null)
    {
        return $this->apiTry(function () use ($inmateId) {
            $data = $this->request->getJSON(true);

            if (empty($data)) {
                return $this->respond(ApiResponse::error('No data provided.', 422), 422);
            }

            $result = $this->releaseInmate->execute((int) $inmateId, $data);

            return $this->respondCreated(ApiResponse::success($result, 'Inmate released', 201));
        });
    }
}
