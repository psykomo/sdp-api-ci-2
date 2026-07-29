<?php

namespace App\Libraries;

use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use DomainException;
use Throwable;

/**
 * Shared exception → JSON envelope mapping for thin API controllers.
 *
 * Usage inside a controller action:
 *   return $this->apiTry(fn () => $this->respond(ApiResponse::success(...)));
 */
trait MapsApiExceptions
{
    /**
     * @param callable(): ResponseInterface $operation
     */
    protected function apiTry(callable $operation): ResponseInterface
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            return $this->mapApiException($e);
        }
    }

    protected function mapApiException(Throwable $e): ResponseInterface
    {
        if ($e instanceof PageNotFoundException) {
            return $this->respond(ApiResponse::error($e->getMessage(), 404), 404);
        }

        if ($e instanceof ValidationException) {
            return $this->respond(
                ApiResponse::error($e->getMessage(), 422, $e->getErrors()),
                422,
            );
        }

        if ($e instanceof DomainException) {
            return $this->respond(ApiResponse::error($e->getMessage(), 422), 422);
        }

        if ($e instanceof AuthenticationException) {
            return $this->respond(ApiResponse::error($e->getMessage(), 401), 401);
        }

        throw $e;
    }
}
