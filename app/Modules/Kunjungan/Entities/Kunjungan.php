<?php

namespace App\Modules\Kunjungan\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int         $organization_id
 * @property int|null    $inmate_id
 * @property string      $visitor_name
 * @property string|null $visitor_id_number
 * @property string      $visited_at
 * @property string      $status
 * @property string|null $notes
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class Kunjungan extends Entity
{
    protected $dates = ['visited_at', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'              => 'integer',
        'organization_id' => 'integer',
        'inmate_id'       => '?integer',
        'status'          => 'string',
    ];

    public function setVisitorName(string $name): static
    {
        $this->attributes['visitor_name'] = trim($name);

        return $this;
    }
}
