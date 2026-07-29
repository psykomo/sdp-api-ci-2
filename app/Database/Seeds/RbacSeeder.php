<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds minimal RBAC catalog, one Kanwil + Lapas, and demo permissions.
 */
class RbacSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('roles')->insertBatch([
            [
                'key'         => 'superadmin',
                'name'        => 'Super Admin',
                'description' => 'Full access within assigned organizations',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'operator',
                'name'        => 'Operator',
                'description' => 'Day-to-day inmate operations',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'viewer',
                'name'        => 'Viewer',
                'description' => 'Read-only access',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);

        $permissions = [
            ['key' => 'inmate.read', 'name' => 'Read inmates'],
            ['key' => 'inmate.write', 'name' => 'Create/update inmates'],
            ['key' => 'inmate.delete', 'name' => 'Delete inmates'],
            ['key' => 'inmate.release', 'name' => 'Release inmates'],
            ['key' => 'inmate.transfer', 'name' => 'Transfer inmates between units'],
            ['key' => 'visit.read', 'name' => 'Read visits'],
            ['key' => 'visit.write', 'name' => 'Create/update visits'],
            ['key' => 'visit.delete', 'name' => 'Delete visits'],
            ['key' => 'user.read', 'name' => 'Read users'],
            ['key' => 'user.write', 'name' => 'Create/update users'],
            ['key' => 'user.delete', 'name' => 'Delete users'],
        ];

        foreach ($permissions as &$permission) {
            $permission['description'] = $permission['name'];
            $permission['created_at']  = $now;
            $permission['updated_at']  = $now;
        }
        unset($permission);

        $this->db->table('permissions')->insertBatch($permissions);

        $roleRows = $this->db->table('roles')->get()->getResultArray();
        $permRows = $this->db->table('permissions')->get()->getResultArray();
        $rolesByKey = array_column($roleRows, 'id', 'key');
        $permsByKey = array_column($permRows, 'id', 'key');

        $assignments = [];
        foreach ($permsByKey as $permId) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['superadmin'],
                'permission_id' => (int) $permId,
            ];
        }
        foreach ([
            'inmate.read', 'inmate.write', 'inmate.release', 'inmate.transfer',
            'visit.read', 'visit.write',
            'user.read',
        ] as $key) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['operator'],
                'permission_id' => (int) $permsByKey[$key],
            ];
        }
        foreach (['inmate.read', 'visit.read', 'user.read'] as $key) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['viewer'],
                'permission_id' => (int) $permsByKey[$key],
            ];
        }
        $this->db->table('role_permissions')->insertBatch($assignments);

        $this->db->table('organizations')->insert([
            'parent_id'  => null,
            'code'       => 'KW-DKI',
            'name'       => 'Kanwil DKI Jakarta',
            'type'       => 'kanwil',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $kanwilId = (int) $this->db->insertID();

        $this->db->table('organizations')->insert([
            'parent_id'  => $kanwilId,
            'code'       => 'LP-CIPINANG',
            'name'       => 'Lapas Cipinang',
            'type'       => 'lapas',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->db->table('organizations')->insert([
            'parent_id'  => $kanwilId,
            'code'       => 'RT-SALEMBA',
            'name'       => 'Rutan Salemba',
            'type'       => 'rutan',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
