<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendUsersAndCreateApiTokens extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'email',
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'updated_at',
            ],
        ]);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'api',
            ],
            'token_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('api_tokens');
    }

    public function down()
    {
        $this->forge->dropTable('api_tokens', true);
        $this->forge->dropColumn('users', ['password_hash', 'deleted_at']);
    }
}
