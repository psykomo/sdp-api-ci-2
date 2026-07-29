<?php

namespace App\Modules\Mutasi\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMutasisTable extends Migration
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
            'inmate_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'from_organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'to_organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'transferred_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'transferred_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('inmate_id');
        $this->forge->addKey('from_organization_id');
        $this->forge->addKey('to_organization_id');
        $this->forge->addKey('transferred_at');
        $this->forge->addForeignKey('inmate_id', 'wbp', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('from_organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('to_organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('transferred_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inmate_transfers');
    }

    public function down()
    {
        $this->forge->dropTable('inmate_transfers');
    }
}
