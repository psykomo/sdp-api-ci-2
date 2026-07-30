<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Creates a demo operator bound to two units with a known password.
 *
 * Email:    operator@sdp.local
 * Password: password
 */
class DemoAuthSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('users')->where('email', 'operator@sdp.local')->get()->getRowArray();

        if ($existing === null) {
            $this->db->table('users')->insert([
                'name'          => 'Demo Operator',
                'email'         => 'operator@sdp.local',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
                'phone'         => null,
                'status'        => 'active',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $this->db->insertID();
        } else {
            $userId = (int) $existing['id'];
            $this->db->table('users')->where('id', $userId)->update([
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
                'status'        => 'active',
                'updated_at'    => $now,
            ]);
        }

        $role = $this->db->table('roles')->where('key', 'operator')->get()->getRowArray();

        if ($role === null) {
            return;
        }

        $organizations = $this->db->table('organizations')
            ->whereIn('code', ['KW-DKI', '093', '604', 'LP-CIPINANG', 'RT-SALEMBA'])
            ->get()
            ->getResultArray();

        foreach ($organizations as $organization) {
            $assignment = $this->db->table('user_organization_roles')
                ->where('user_id', $userId)
                ->where('organization_id', (int) $organization['id'])
                ->where('role_id', (int) $role['id'])
                ->get()
                ->getRowArray();

            if ($assignment === null) {
                $this->db->table('user_organization_roles')->insert([
                    'user_id'         => $userId,
                    'organization_id' => (int) $organization['id'],
                    'role_id'         => (int) $role['id'],
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }
}
