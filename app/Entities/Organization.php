<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int|null    $parent_id
 * @property string      $code
 * @property string      $name
 * @property string      $type
 * @property string      $status
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class Organization extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'        => 'integer',
        'parent_id' => '?integer',
        'type'      => 'string',
        'status'    => 'string',
    ];

    public function isKanwil(): bool
    {
        return ($this->attributes['type'] ?? '') === 'kanwil';
    }

    public function isUnit(): bool
    {
        return in_array($this->attributes['type'] ?? '', ['lapas', 'rutan'], true);
    }
}
