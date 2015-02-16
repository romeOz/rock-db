<?php

namespace rockunit\migrations;

use rock\db\Migration;
use rock\db\Schema;

class SessionsMigration extends Migration
{
    public $table = 'sessions';
    public function up()
    {
        switch ($this->connection->driverName) {
            case 'pgsql':
                $sql = "SELECT * FROM pg_catalog.pg_tables WHERE tablename LIKE '{$this->table}'";
                break;
            case 'sqlite':
                $sql =  "SELECT * FROM sqlite_master WHERE tbl_name LIKE '{$this->table}'";
                //$this->down();
                break;
            default:
                $sql = "SHOW TABLES LIKE '{$this->table}'";
        }

        if ($this->connection->driverName === 'sqlite') {
            if ($this->connection->createCommand($sql)->queryAll()) {
                return;
            }
        } else {
            if ((bool)$this->connection->createCommand($sql)->execute()) {
                return;
            }
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