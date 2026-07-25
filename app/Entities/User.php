<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * User Entity
 *
 * Provides a strongly-typed representation of a User record
 * with accessors, mutators, and computed properties.
 *
 * @see https://codeigniter.com/user_guide/models/entities.html
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string|null $password_hash
 * @property string|null $phone
 * @property string      $status
 * @property string      $created_at
 * @property string      $updated_at
 * @property string|null $deleted_at
 */
class User extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'     => 'integer',
        'status' => 'string',
    ];

    // -----------------------------------------------------------------
    //  MUTATORS — called when setting a value
    // -----------------------------------------------------------------

    /**
     * Normalize the name: trim and title-case.
     */
    public function setName(string $name): static
    {
        $this->attributes['name'] = ucwords(trim($name));

        return $this;
    }

    /**
     * Normalize email to lowercase.
     */
    public function setEmail(string $email): static
    {
        $this->attributes['email'] = strtolower(trim($email));

        return $this;
    }

    /**
     * Default status for new users.
     */
    public function setStatus(?string $status): static
    {
        $this->attributes['status'] = $status ?: 'active';

        return $this;
    }

    // -----------------------------------------------------------------
    //  ACCESSORS — called when getting a value
    // -----------------------------------------------------------------

    /**
     * Return whether the user is active.
     */
    public function isActive(): bool
    {
        return $this->attributes['status'] === 'active';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $data = parent::toArray($onlyChanged, $cast, $recursive);
        unset($data['password_hash']);

        foreach (['created_at', 'updated_at', 'deleted_at'] as $field) {
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
