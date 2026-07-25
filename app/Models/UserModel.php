<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * UserModel
 *
 * Handles all database interactions for the `users` table.
 *
 * @see https://codeigniter.com/user_guide/models/model.html
 */
class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = \App\Entities\User::class;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'name',
        'email',
        'password_hash',
        'phone',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'name'          => 'required|min_length[2]|max_length[100]',
        'email'         => 'required|valid_email|max_length[255]|is_unique[users.email,id,{id}]',
        'password_hash' => 'permit_empty|max_length[255]',
        'phone'         => 'permit_empty|max_length[30]',
        'status'        => 'permit_empty|in_list[active,inactive,suspended]',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'The name field is required.',
            'min_length' => 'The name must be at least 2 characters.',
            'max_length' => 'The name cannot exceed 100 characters.',
        ],
        'email' => [
            'required'    => 'The email field is required.',
            'valid_email' => 'Please provide a valid email address.',
            'is_unique'   => 'This email is already registered.',
            'max_length'  => 'The email cannot exceed 255 characters.',
        ],
        'status' => [
            'in_list' => 'Status must be one of: active, inactive, suspended.',
        ],
    ];

    protected $skipValidation         = false;
    protected bool $allowEmptyInserts = false;
}
