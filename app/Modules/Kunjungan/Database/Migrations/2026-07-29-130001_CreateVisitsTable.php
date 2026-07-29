<?php

namespace App\Modules\Kunjungan\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Thin-module reference: visit records (kunjungan).
 *
 * organization_id is always the owning unit. inmate_id is a soft reference
 * (no FK) so this module stays deployable without requiring Inmate migrations
 * in every environment — enforce "inmate in scope" in the service when needed.
 */
class CreateVisitsTable extends Migration
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
            'inmate_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'visitor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'visitor_id_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'visited_at' => [
                'type' => 'DATETIME',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'scheduled',
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('organization_id');
        $this->forge->addKey('inmate_id');
        $this->forge->addKey('visited_at');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('visits');
    }

    public function down()
    {
        $this->forge->dropTable('visits');
    }
}
