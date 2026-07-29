<?php

namespace App\Modules\Wbp\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property int         $organization_id
 * @property string      $registration_number
 * @property string      $full_name
 * @property string|null $alias_name
 * @property string|null $gender
 * @property string|null $birth_place
 * @property string|null $birth_date
 * @property string      $status
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class Wbp extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'              => 'integer',
        'organization_id' => 'integer',
        'status'          => 'string',
        'birth_date'      => '?string',
    ];

    public function setFullName(string $name): static
    {
        $this->attributes['full_name'] = trim($name);

        return $this;
    }

    public function setRegistrationNumber(string $number): static
    {
        $this->attributes['registration_number'] = strtoupper(trim($number));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $data = parent::toArray($onlyChanged, $cast, $recursive);

        foreach (['created_at', 'updated_at', 'deleted_at', 'birth_date'] as $field) {
            if (isset($data[$field]) && is_object($data[$field]) && method_exists($data[$field], 'toDateTimeString')) {
                $data[$field] = $data[$field]->toDateTimeString();
            } elseif (isset($data[$field]) && $data[$field] instanceof \DateTimeInterface) {
                $data[$field] = $data[$field]->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
