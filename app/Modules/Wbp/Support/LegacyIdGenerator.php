<?php

namespace App\Modules\Wbp\Support;

use CodeIgniter\Database\BaseConnection;

/**
 * App-generated string IDs similar to legacy func helpers (simplified).
 */
final class LegacyIdGenerator
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * {idUpt}{YmdHis}{####}
     */
    public function withUptPrefix(string $idUpt, string $table, string $pkColumn): string
    {
        $prefix = $idUpt . date('YmdHis');
        for ($i = 0; $i < 20; $i++) {
            $id = $prefix . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = $this->db->table($table)->select($pkColumn)->where($pkColumn, $id)->get()->getRowArray();
            if ($exists === null) {
                return $id;
            }
        }

        return $prefix . bin2hex(random_bytes(4));
    }
}
