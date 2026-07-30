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
                'description' => 'Day-to-day WBP operations',
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
            ['key' => 'wbp.read', 'name' => 'Read WBP'],
            ['key' => 'wbp.write', 'name' => 'Create/update WBP'],
            ['key' => 'wbp.delete', 'name' => 'Delete WBP'],
            ['key' => 'wbp.release', 'name' => 'Release WBP (pembebasan)'],
            ['key' => 'wbp.mutasi', 'name' => 'Mutasi WBP between units'],
            ['key' => 'kunjungan.read', 'name' => 'Read kunjungan'],
            ['key' => 'kunjungan.write', 'name' => 'Create/update kunjungan'],
            ['key' => 'kunjungan.delete', 'name' => 'Delete kunjungan'],
            ['key' => 'referensi.read', 'name' => 'Read referensi / master lookups'],
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
            'wbp.read', 'wbp.write', 'wbp.delete', 'wbp.release', 'wbp.mutasi',
            'kunjungan.read', 'kunjungan.write',
            'referensi.read',
            'user.read',
        ] as $key) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['operator'],
                'permission_id' => (int) $permsByKey[$key],
            ];
        }
        foreach (['wbp.read', 'kunjungan.read', 'referensi.read', 'user.read'] as $key) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['viewer'],
                'permission_id' => (int) $permsByKey[$key],
            ];
        }
        $this->db->table('role_permissions')->insertBatch($assignments);

        // For shared-schema pilot, organization.code maps to legacy ID_UPT.
        $this->db->table('organizations')->insert([
            'parent_id'  => null,
            'code'       => 'KW-DKI',
            'name'       => 'Kanwil (all UPT — no ID_UPT filter)',
            'type'       => 'kanwil',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $kanwilId = (int) $this->db->insertID();

        foreach (
            [
                ['093', 'UPT 093 (from seed perkara)', 'lapas'],
                ['604', 'UPT 604 (from seed perkara)', 'lapas'],
                ['LP-CIPINANG', 'Lapas Cipinang (demo code)', 'lapas'],
                ['RT-SALEMBA', 'Rutan Salemba (demo code)', 'rutan'],
            ] as [$code, $name, $type]
        ) {
            $this->db->table('organizations')->insert([
                'parent_id'  => $kanwilId,
                'code'       => $code,
                'name'       => $name,
                'type'       => $type,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
