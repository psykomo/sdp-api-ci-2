<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ForceJson Filter
 *
 * Ensures all API responses are JSON by setting the appropriate
 * request headers and content type.
 */
class ForceJson implements FilterInterface
{
    /**
     * Before the controller method is called.
     *
     * Sets the request format to JSON so content negotiation
     * always resolves to JSON responses.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Force the request to treat all incoming data as JSON
        $request->setHeader('Accept', 'application/json');
    }

    /**
     * After the controller method is called.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Ensure the Content-Type header is set to JSON
        if (! $response->hasHeader('Content-Type')) {
            $response->setHeader('Content-Type', 'application/json');
        }

        return $response;
    }
}
