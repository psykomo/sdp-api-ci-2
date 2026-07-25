<?php

namespace App\Libraries;

/**
 * Uniform JSON envelope helpers for API controllers.
 *
 * Controllers may still use ResponseTrait; prefer these helpers for
 * consistent success / error / paginated payloads across modules.
 */
class ApiResponse
{
    /**
     * @param array<string, mixed>|object|list<mixed>|null $data
     * @return array<string, mixed>
     */
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): array
    {
        return [
            'status'  => 'success',
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ];
    }

    /**
     * @param array<string, mixed>|list<string>|null $errors
     * @return array<string, mixed>
     */
    public static function error(string $message, int $code = 400, mixed $errors = null): array
    {
        $payload = [
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $payload;
    }

    /**
     * @param list<mixed>|array<int, mixed> $items
     * @param array{page?: int, perPage?: int, total?: int, pageCount?: int} $meta
     * @return array<string, mixed>
     */
    public static function paginated(array $items, array $meta = [], string $message = 'OK'): array
    {
        return [
            'status'  => 'success',
            'code'    => 200,
            'message' => $message,
            'data'    => $items,
            'meta'    => [
                'page'      => $meta['page'] ?? 1,
                'perPage'   => $meta['perPage'] ?? count($items),
                'total'     => $meta['total'] ?? count($items),
                'pageCount' => $meta['pageCount'] ?? 1,
            ],
        ];
    }
}
