<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

/**
 * Ping Controller
 *
 * Simple health-check / ping endpoint for the API.
 *
 * Access at: GET /api/ping  or  GET /api/v1/ping
 *
 * @see https://codeigniter.com/user_guide/guides/api/first-endpoint.html
 */
class Ping extends BaseController
{
    /**
     * Default method — responds with a simple status payload.
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function index()
    {
        return $this->respond([
            'status'  => 'ok',
            'message' => 'pong',
            'version' => config('App')->apiVersion ?? 'v1',
            'time'    => date('c'),
            'php'     => PHP_VERSION,
        ]);
    }
}
