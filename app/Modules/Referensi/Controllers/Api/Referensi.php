<?php

namespace App\Modules\Referensi\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Libraries\MapsApiExceptions;
use App\Modules\Referensi\Services\ReferensiService;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;

/**
 * R0 — read-only referensi / master lookups (legacy tables).
 */
class Referensi extends ResourceController
{
    use MapsApiExceptions;

    protected $format = 'json';

    public function __construct(
        protected ?ReferensiService $referensi = null,
    ) {
        $this->referensi ??= Services::referensiService();
    }

    /** GET /api/v1/referensi/jenis-registrasi */
    public function jenisRegistrasi()
    {
        return $this->apiTry(function () {
            $activeOnly = $this->request->getGet('active') !== '0';
            $isTahanan  = $this->request->getGet('is_tahanan');
            $flag       = $isTahanan === null || $isTahanan === '' ? null : (int) $isTahanan;

            $rows = $this->referensi->listJenisRegistrasi($activeOnly, $flag);

            return $this->respond(ApiResponse::success($rows));
        });
    }

    /** GET /api/v1/referensi/groups */
    public function groups()
    {
        return $this->apiTry(function () {
            return $this->respond(ApiResponse::success($this->referensi->listLookupGroups()));
        });
    }

    /** GET /api/v1/referensi/lookups?group=Agama */
    public function lookups()
    {
        return $this->apiTry(function () {
            $group = (string) ($this->request->getGet('group') ?? '');
            if ($group === '') {
                return $this->respond(ApiResponse::error('Query parameter group is required.', 422), 422);
            }

            return $this->respond(ApiResponse::success($this->referensi->listLookupsByGroup($group)));
        });
    }

    /** GET /api/v1/referensi/lookups/(:segment) */
    public function showLookup($id = null)
    {
        return $this->apiTry(function () use ($id) {
            return $this->respond(ApiResponse::success($this->referensi->findLookupOrFail((string) $id)));
        });
    }

    /** GET /api/v1/referensi/upt */
    public function upt()
    {
        return $this->apiTry(function () {
            $search = $this->request->getGet('search');
            $limit  = (int) ($this->request->getGet('limit') ?: 100);

            return $this->respond(ApiResponse::success(
                $this->referensi->listUpt($search !== null ? (string) $search : null, $limit),
            ));
        });
    }

    /** GET /api/v1/referensi/upt/(:segment) */
    public function showUpt($id = null)
    {
        return $this->apiTry(function () use ($id) {
            return $this->respond(ApiResponse::success($this->referensi->findUptOrFail((string) $id)));
        });
    }
}
