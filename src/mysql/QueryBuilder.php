<?php
namespace rock\db\mysql;

use rock\db\common\DbException;
use rock\db\Expression;

/**
 * QueryBuilder is the query builder for MySQL databases.
 */
class QueryBuilder extends \rock\db\QueryBuilder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $typeMap = [
        Schema::TYPE_PK => 'int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
        Schema::TYPE_BIGPK => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_CHAR => 'char(1)',
        Schema::TYPE_TEXT => 'text',
        Schema::TYPE_SMALLINT => 'smallint(6)',
        Schema::TYPE_INTEGER => 'int(11)',
        Schema::TYPE_BIGINT => 'bigint(20)',
        Schema::TYPE_FLOAT => 'float',
        Schema::TYPE_DOUBLE => 'double',
        Schema::TYPE_DECIMAL => 'decimal(10,0)',
        Schema::TYPE_DATETIME => 'datetime',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
		Schema::TYPE_BINARY => 'binary(1)',
        Schema::TYPE_BLOB => 'blob',
        Schema::TYPE_BOOLEAN => 'tinyint(1)',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];

    /**
     * @inheritdoc
     */
    public function createTable($table, array $columns, $options = null, $exists = false)
    {
        $cols = $this->calculateColumns($columns);
        $exists = $exists === true ? ' IF NOT EXISTS ' : null;
        $sql = "CREATE TABLE {$exists}" . $this->connection->quoteTableName($table) . " (\n" . implode(",\n", $cols) . "\n)";

        return $options === null ? $sql : $sql . ' ' . $options;
    }

    /**
     * @inheritdoc
     */
    public function dropTable($table, $exists = false)
    {
        $exists = $exists === true ? ' IF EXISTS ' : null;
        return "DROP TABLE {$exists}" . $this->connection->quoteTableName($table);
    }

    /**
     * Builds a SQL statement for renaming a column.
     *
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB column.
     * @throws DbException
     */
    public function renameColumn($table, $oldName, $newName)
    {
        $quotedTable = $this->connection->quoteTableName($table);
        $row = $this->connection->createCommand('SHOW CREATE TABLE ' . $quotedTable)->queryOne();
        if ($row === false) {
            throw new DbException("Unable to find column '$oldName' in table '$table'.");
        }
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }
        if (preg_match_all('/^\s*`(.*?)`\s+(.*?),?$/m', $sql, $matches)) {
            foreach ($matches[1] as $i => $c) {
                if ($c === $oldName) {
                    return "ALTER TABLE $quotedTable CHANGE "
                        . $this->connection->quoteColumnName($oldName) . ' '
                        . $this->connection->quoteColumnName($newName) . ' '
                        . $matches[2][$i];
                }
            }
        }
        // try to give back a SQL anyway
        return "ALTER TABLE $quotedTable CHANGE "
            . $this->connection->quoteColumnName($oldName) . ' '
            . $this->connection->quoteColumnName($newName);
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a foreign key constraint.
     */
    public function dropForeignKey($name, $table)
    {
        return 'ALTER TABLE ' . $this->connection->quoteTableName($table)
            . ' DROP FOREIGN KEY ' . $this->connection->quoteColumnName($name);
    }

    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     */
    public function dropPrimaryKey($name, $table)
    {
        return 'ALTER TABLE ' . $this->connection->quoteTableName($table) . ' DROP PRIMARY KEY';
    }

    /**
     * Creates a SQL statement for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or 1.
     *
     * @param string $tableName the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have a value 1.
     * @return string the SQL statement for resetting sequence
     * @throws DbException if the table does not exist or there is no sequence associated with the table.
     */
    public function resetSequence($tableName, $value = null)
    {
        $table = $this->connection->getTableSchema($tableName);
        if ($table !== null && $table->sequenceName !== null) {
            $tableName = $this->connection->quoteTableName($tableName);
            if ($value === null) {
                $key = reset($table->primaryKey);
                $value = $this->connection->createCommand("SELECT MAX(`$key`) FROM $tableName")->queryScalar() + 1;
            } else {
                $value = (int) $value;
            }

            return "ALTER TABLE $tableName AUTO_INCREMENT=$value";
        } elseif ($table === null) {
            throw new DbException("Table not found: $tableName");
        } else {
            throw new DbException("There is no sequence associated with table '$tableName'.");
        }
    }

    /**
     * Builds a SQL statement for enabling or disabling integrity check.
     * @param boolean $check whether to turn on or off the integrity check.
     * @param string $table the table name. Meaningless for MySQL.
     * @param string $schema the schema of the tables. Meaningless for MySQL.
     * @return string the SQL statement for checking integrity
     */
    public function checkIntegrity($check = true, $schema = '', $table = '')
    {
        return 'SET FOREIGN_KEY_CHECKS = ' . ($check ? 1 : 0);
    }

    /**
     * @inheritdoc
     */
    public function buildLimit($limit, $offset)
    {
        $sql = '';
        if ($this->hasLimit($limit)) {
            $sql = 'LIMIT ' . $limit;
            if ($this->hasOffset($offset)) {
                $sql .= ' OFFSET ' . $offset;
            }
        } elseif ($this->hasOffset($offset)) {
            // limit is not optional in MySQL
            // http://stackoverflow.com/a/271650/1106908
            // http://dev.mysql.com/doc/refman/5.0/en/select.html#idm47619502796240
            $sql = "LIMIT $offset, 18446744073709551615"; // 2^64-1
        }

        return $sql;
    }

    /**
     * @inheritdoc
     */
    public function insert($table, array $columns, array &$params)
    {
        $schema = $this->connection->getSchema();
        if (($tableSchema = $schema->getTableSchema($table)) !== null) {
            $columnSchemas = $tableSchema->columns;
        } else {
            $columnSchemas = [];
        }
        $names = [];
        $placeholders = [];
        foreach ($columns as $name => $value) {
            $names[] = $schema->quoteColumnName($name);
            if ($value instanceof Expression) {
                $placeholders[] = $value->expression;
                foreach ($value->params as $n => $v) {
                    $params[$n] = $v;
                }
            } else {
                $phName = self::PARAM_PREFIX . count($params);
                $placeholders[] = $phName;
                $params[$phName] = !is_array($value) && isset($columnSchemas[$name]) ? $columnSchemas[$name]->dbTypecast($value) : $value;
            }
        }
        if (empty($names) && $tableSchema !== null) {
            $columns = !empty($tableSchema->primaryKey) ? $tableSchema->primaryKey : reset($tableSchema->columns)->name;
            foreach ($columns as $name) {
                $names[] = $schema->quoteColumnName($name);
                $placeholders[] = 'DEFAULT';
            }
        }
        return 'INSERT INTO ' . $schema->quoteTableName($table)
        . (!empty($names) ? ' (' . implode(', ', $names) . ')' : '')
        . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : ' DEFAULT VALUES');
    }
}
