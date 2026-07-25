<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiTokenModel extends Model
{
    protected $table            = 'api_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'user_id',
        'name',
        'token_hash',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
