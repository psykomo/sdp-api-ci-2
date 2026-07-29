<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Deterministic fixture for API feature tests (single topology).
 *
 * Users (password for all: "password"):
 * - admin@cipinang.test   superadmin @ LP-CIPINANG  (full perms)
 * - op@cipinang.test      operator   @ LP-CIPINANG
 * - op@salemba.test       operator   @ RT-SALEMBA
 * - viewer@cipinang.test  viewer     @ LP-CIPINANG  (read-only)
 */
class ApiFeatureSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');
        $db  = $this->db;

        $db->table('roles')->insertBatch([
            [
                'key'         => 'superadmin',
                'name'        => 'Super Admin',
                'description' => 'Full access',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'operator',
                'name'        => 'Operator',
                'description' => 'Day-to-day ops',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'viewer',
                'name'        => 'Viewer',
                'description' => 'Read-only',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);

        $permissions = [
            'wbp.read',
            'wbp.write',
            'wbp.delete',
            'wbp.release',
            'wbp.mutasi',
            'kunjungan.read',
            'kunjungan.write',
            'kunjungan.delete',
            'user.read',
            'user.write',
            'user.delete',
        ];

        $permRows = [];
        foreach ($permissions as $key) {
            $permRows[] = [
                'key'         => $key,
                'name'        => $key,
                'description' => $key,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        $db->table('permissions')->insertBatch($permRows);

        $rolesByKey = array_column($db->table('roles')->get()->getResultArray(), 'id', 'key');
        $permsByKey = array_column($db->table('permissions')->get()->getResultArray(), 'id', 'key');

        $assignments = [];
        foreach ($permsByKey as $permId) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['superadmin'],
                'permission_id' => (int) $permId,
            ];
        }
        foreach ([
            'wbp.read', 'wbp.write', 'wbp.release', 'wbp.mutasi',
            'kunjungan.read', 'kunjungan.write',
            'user.read',
        ] as $key) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['operator'],
                'permission_id' => (int) $permsByKey[$key],
            ];
        }
        foreach (['wbp.read', 'kunjungan.read', 'user.read'] as $key) {
            $assignments[] = [
                'role_id'       => (int) $rolesByKey['viewer'],
                'permission_id' => (int) $permsByKey[$key],
            ];
        }
        $db->table('role_permissions')->insertBatch($assignments);

        $db->table('organizations')->insert([
            'parent_id'  => null,
            'code'       => 'KW-DKI',
            'name'       => 'Kanwil DKI Jakarta',
            'type'       => 'kanwil',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $kanwilId = (int) $db->insertID();

        $db->table('organizations')->insert([
            'parent_id'  => $kanwilId,
            'code'       => 'LP-CIPINANG',
            'name'       => 'Lapas Cipinang',
            'type'       => 'lapas',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cipinangId = (int) $db->insertID();

        $db->table('organizations')->insert([
            'parent_id'  => $kanwilId,
            'code'       => 'RT-SALEMBA',
            'name'       => 'Rutan Salemba',
            'type'       => 'rutan',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $salembaId = (int) $db->insertID();

        $passwordHash = password_hash('password', PASSWORD_DEFAULT);

        $users = [
            ['name' => 'Cipinang Admin', 'email' => 'admin@cipinang.test', 'role' => 'superadmin', 'org' => $cipinangId],
            ['name' => 'Cipinang Operator', 'email' => 'op@cipinang.test', 'role' => 'operator', 'org' => $cipinangId],
            ['name' => 'Salemba Operator', 'email' => 'op@salemba.test', 'role' => 'operator', 'org' => $salembaId],
            ['name' => 'Cipinang Viewer', 'email' => 'viewer@cipinang.test', 'role' => 'viewer', 'org' => $cipinangId],
        ];

        foreach ($users as $user) {
            $db->table('users')->insert([
                'name'          => $user['name'],
                'email'         => $user['email'],
                'password_hash' => $passwordHash,
                'phone'         => null,
                'status'        => 'active',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $db->insertID();

            $db->table('user_organization_roles')->insert([
                'user_id'         => $userId,
                'organization_id' => $user['org'],
                'role_id'         => (int) $rolesByKey[$user['role']],
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // Inactive account for auth negative tests
        $db->table('users')->insert([
            'name'          => 'Inactive User',
            'email'         => 'inactive@cipinang.test',
            'password_hash' => $passwordHash,
            'phone'         => null,
            'status'        => 'inactive',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $inactiveId = (int) $db->insertID();
        $db->table('user_organization_roles')->insert([
            'user_id'         => $inactiveId,
            'organization_id' => $cipinangId,
            'role_id'         => (int) $rolesByKey['operator'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
}
