<?php

namespace App\Modules\Inmate\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Inmate\Actions\ReleaseInmate;
use CodeIgniter\RESTful\ResourceController;

/**
 * Inmate release (pembebasan) endpoint.
 *
 * Process controllers may call an Action directly when the process is
 * HTTP-facing only. Other modules should go through InmateService if/when
 * the process is exposed on the facade.
 */
class InmateReleases extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ReleaseInmate $releaseInmate = new ReleaseInmate(),
    ) {
    }

    /**
     * POST /api/v1/inmates/{inmateId}/releases
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
