<?php

namespace App\Modules\Transfer\Entities;

use CodeIgniter\Entity\Entity;

class InmateTransfer extends Entity
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
