<?php

namespace rockunit\migrations;

use rock\db\Migration;
use rock\db\Schema;

class SessionsMigration extends Migration
{
    public $table = 'sessions';
    public function up()
    {
        $sql = $this->connection->driverName === 'pgsql'
            ? "SELECT * FROM pg_catalog.pg_tables WHERE tablename LIKE '{$this->table}'"
            : "SHOW TABLES LIKE '{$this->table}'";

        if ((bool)$this->connection->createCommand($sql)->execute()) {
            return;
        }

        $tableOptions = null;
        if ($this->connection->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=InnoDB';
        }

        $this->createTable(
            $this->table,
            [
                'id' => Schema::TYPE_CHAR . '(40) NOT NULL',
                'expire' => Schema::TYPE_INTEGER,
                'data' => $this->connection->driverName === 'pgsql' ? 'bytea': Schema::TYPE_BLOB,
            ],
            $tableOptions,
            true
        );
        $this->addPrimaryKey("{$this->table}_id",$this->table, 'id');
    }

    public function down()
    {
        $this->dropTable($this->table);
    }
} 