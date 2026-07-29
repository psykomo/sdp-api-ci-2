<?php

namespace App\Modules\Wbp\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWbpReleasesTable extends Migration
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
            'organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'release_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'release_date' => [
                'type' => 'DATE',
            ],
            'decree_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'released_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
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
        $this->forge->addKey('inmate_id');
        $this->forge->addKey('organization_id');
        $this->forge->addKey('release_date');
        $this->forge->addForeignKey('inmate_id', 'wbp', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('released_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inmate_releases');
    }

    public function down()
    {
        $this->forge->dropTable('inmate_releases');
    }
}
