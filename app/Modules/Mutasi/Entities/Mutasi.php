<?php

namespace App\Modules\Mutasi\Entities;

use CodeIgniter\Entity\Entity;

class Mutasi extends Entity
{
    protected $dates = ['transferred_at', 'created_at', 'updated_at'];

    protected $casts = [
        'id'                   => 'integer',
        'inmate_id'            => 'integer',
        'from_organization_id' => 'integer',
        'to_organization_id'   => 'integer',
        'transferred_by'       => '?integer',
    ];
}
