<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'entity_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'entity_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'meta' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('organization_id');
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('audit_logs');
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs');
    }
}
