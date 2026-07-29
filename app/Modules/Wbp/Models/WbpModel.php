<?php

namespace App\Modules\Wbp\Models;

use CodeIgniter\Model;

class WbpModel extends Model
{
    protected $table            = 'inmates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = \App\Modules\Wbp\Entities\Wbp::class;
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'organization_id',
        'registration_number',
        'full_name',
        'alias_name',
        'gender',
        'birth_place',
        'birth_date',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'organization_id'     => 'required|is_natural_no_zero',
        'registration_number' => 'required|max_length[50]',
        'full_name'           => 'required|min_length[2]|max_length[255]',
        'alias_name'          => 'permit_empty|max_length[255]',
        'gender'              => 'permit_empty|in_list[L,P]',
        'birth_place'         => 'permit_empty|max_length[150]',
        'birth_date'          => 'permit_empty|valid_date',
        'status'              => 'permit_empty|in_list[active,released,transferred,deceased]',
    ];

    protected $validationMessages = [
        'registration_number' => [
            'required' => 'Registration number (nomor registrasi) is required.',
        ],
        'full_name' => [
            'required' => 'Full name is required.',
        ],
    ];
}
