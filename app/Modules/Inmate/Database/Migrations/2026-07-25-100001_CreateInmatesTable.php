<?php

namespace App\Modules\Inmate\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInmatesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'registration_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'alias_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'gender' => [
                'type'       => 'CHAR',
                'constraint' => 1,
                'null'       => true,
                'comment'    => 'L|P',
            ],
            'birth_place' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'birth_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'active',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['organization_id', 'registration_number']);
        $this->forge->addKey('organization_id');
        $this->forge->addKey('status');
        $this->forge->addKey('full_name');
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('inmates');
    }

    public function down()
    {
        $this->forge->dropTable('inmates');
    }
}
