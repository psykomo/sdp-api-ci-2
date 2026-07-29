<?php

namespace App\Modules\Wbp\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int         $inmate_id
 * @property int         $organization_id
 * @property string      $release_type
 * @property string      $release_date
 * @property string|null $decree_number
 * @property int|null    $released_by
 * @property string|null $notes
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class WbpRelease extends Entity
{
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'id'              => 'integer',
        'inmate_id'       => 'integer',
        'organization_id' => 'integer',
        'released_by'     => '?integer',
    ];
}
