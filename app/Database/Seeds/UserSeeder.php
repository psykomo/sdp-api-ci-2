<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

/**
 * UserSeeder
 *
 * Seeds the `users` table with sample data for development.
 */
class UserSeeder extends Seeder
{
    public function run()
    {
        $faker = Factory::create();

        $data = [];

        for ($i = 0; $i < 25; $i++) {
            $data[] = [
                'name'       => $faker->name(),
                'email'      => $faker->unique()->safeEmail(),
                'phone'      => $faker->optional(0.7)->phoneNumber(),
                'status'     => $faker->randomElement(['active', 'active', 'active', 'inactive', 'suspended']),
                'created_at' => $faker->dateTimeThisYear()->format('Y-m-d H:i:s'),
                'updated_at' => $faker->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('users')->insertBatch($data);
    }
}
