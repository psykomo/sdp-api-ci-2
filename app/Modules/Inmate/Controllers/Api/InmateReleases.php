<?php

namespace App\Modules\Inmate\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Modules\Inmate\Actions\ReleaseInmate;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\RESTful\ResourceController;
use DomainException;
use RuntimeException;

/**
 * Inmate release (pembebasan) endpoint.
 *
 * Thin HTTP layer: it validates transport concerns, calls the ReleaseInmate
 * action (the actual business process), and maps exceptions to status codes.
 * One controller per process family keeps each one small and focused.
 */
class InmateReleases extends ResourceController
{
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
        $data = $this->request->getJSON(true);

        if (empty($data)) {
            return $this->respond(ApiResponse::error('No data provided.', 422), 422);
        }

        try {
            $result = $this->releaseInmate->execute((int) $inmateId, $data);
        } catch (PageNotFoundException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        } catch (DomainException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 422), 422);
        } catch (RuntimeException $e) {
            return $this->respond(ApiResponse::error($e->getMessage(), 422), 422);
        }

        return $this->respondCreated(ApiResponse::success($result, 'Inmate released', 201));
    }
}
