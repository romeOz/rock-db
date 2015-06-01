<?php
namespace rock\db\mssql;

use rock\db\common\DbException;
use rock\db\Query;

/**
 * QueryBuilder is the query builder for MS SQL Server databases (version 2008 and above).
 *
 * @author Timur Ruziev <resurtm@gmail.com>
 * @since 2.0
 */
class QueryBuilder extends \rock\db\QueryBuilder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $typeMap = [
        Schema::TYPE_PK => 'int IDENTITY PRIMARY KEY',
        Schema::TYPE_BIGPK => 'bigint IDENTITY PRIMARY KEY',
        Schema::TYPE_STRING => 'varchar(255)',
        Schema::TYPE_TEXT => 'text',
        Schema::TYPE_SMALLINT => 'smallint',
        Schema::TYPE_INTEGER => 'int',
        Schema::TYPE_BIGINT => 'bigint',
        Schema::TYPE_FLOAT => 'float',
        Schema::TYPE_DECIMAL => 'decimal',
        Schema::TYPE_DATETIME => 'datetime',
        Schema::TYPE_TIMESTAMP => 'timestamp',
        Schema::TYPE_TIME => 'time',
        Schema::TYPE_DATE => 'date',
        Schema::TYPE_BINARY => 'binary(1)',
        Schema::TYPE_BOOLEAN => 'bit',
        Schema::TYPE_MONEY => 'decimal(19,4)',
    ];

    /**
     * @inheritdoc
     */
    public function createTable($table, array $columns, $options = null, $exists = false)
    {
        $cols = $this->calculateColumns($columns);
        $exists = $exists === true ? "IF OBJECT_ID('{$table}', 'U') IS NOT NULL " : null;
        $sql = "{$exists}CREATE TABLE " . $this->connection->quoteTableName($table) . " (\n" . implode(",\n", $cols) . "\n)";

        return $options === null ? $sql : $sql . ' ' . $options;
    }

    /**
     * @inheritdoc
     */
    public function dropTable($table, $exists = false)
    {
        $exists = $exists === true ? "IF OBJECT_ID('{$table}', 'U') IS NOT NULL " : null;
        return "{$exists}DROP TABLE " . $this->connection->quoteTableName($table);
    }

    /**
     * @inheritdoc
     */
    public function buildOrderByAndLimit($sql, $orderBy, $limit, $offset)
    {
        if (!$this->hasOffset($offset) && !$this->hasLimit($limit)) {
            $orderBy = $this->buildOrderBy($orderBy);
            return $orderBy === '' ? $sql : $sql . $this->separator . $orderBy;
        }

        if ($this->isOldMssql()) {
            return $this->oldbuildOrderByAndLimit($sql, $orderBy, $limit, $offset);
        } else {
            return $this->newBuildOrderByAndLimit($sql, $orderBy, $limit, $offset);
        }
    }

    /**
     * Builds the ORDER BY/LIMIT/OFFSET clauses for SQL SERVER 2012 or newer.
     * @param string $sql the existing SQL (without ORDER BY/LIMIT/OFFSET)
     * @param array $orderBy the order by columns. See {@see \rock\db\Query::$orderBy} for more details on how to specify this parameter.
     * @param integer $limit the limit number. See {@see \rock\db\Query::$limit} for more details.
     * @param integer $offset the offset number. See {@see \rock\db\Query::$offset} for more details.
     * @return string the SQL completed with ORDER BY/LIMIT/OFFSET (if any)
     */
    protected function newBuildOrderByAndLimit($sql, $orderBy, $limit, $offset)
    {
        $orderBy = $this->buildOrderBy($orderBy);
        if ($orderBy === '') {
            // ORDER BY clause is required when FETCH and OFFSET are in the SQL
            $orderBy = 'ORDER BY (SELECT NULL)';
        }
        $sql .= $this->separator . $orderBy;

        // http://technet.microsoft.com/en-us/library/gg699618.aspx
        $offset = $this->hasOffset($offset) ? $offset : '0';
        $sql .= $this->separator . "OFFSET $offset ROWS";
        if ($this->hasLimit($limit)) {
            $sql .= $this->separator . "FETCH NEXT $limit ROWS ONLY";
        }

        return $sql;
    }

    /**
     * Builds the ORDER BY/LIMIT/OFFSET clauses for SQL SERVER 2005 to 2008.
     * @param string $sql the existing SQL (without ORDER BY/LIMIT/OFFSET)
     * @param array $orderBy the order by columns. See {@see \rock\db\Query::$orderBy} for more details on how to specify this parameter.
     * @param integer $limit the limit number. See {@see \rock\db\Query::$limit} for more details.
     * @param integer $offset the offset number. See {@see \rock\db\Query::$offset} for more details.
     * @return string the SQL completed with ORDER BY/LIMIT/OFFSET (if any)
     */
    protected function oldBuildOrderByAndLimit($sql, $orderBy, $limit, $offset)
    {
        $orderBy = $this->buildOrderBy($orderBy);
        if ($orderBy === '') {
            // ROW_NUMBER() requires an ORDER BY clause
            $orderBy = 'ORDER BY (SELECT NULL)';
        }

        $sql = preg_replace('/^([\s(])*SELECT(\s+DISTINCT)?(?!\s*TOP\s*\()/i', "\\1SELECT\\2 rowNum = ROW_NUMBER() over ($orderBy),", $sql);

        if ($this->hasLimit($limit)) {
            $sql = "SELECT TOP $limit * FROM ($sql) sub";
        } else {
            $sql = "SELECT * FROM ($sql) sub";
        }
        if ($this->hasOffset($offset)) {
            $sql .= $this->separator . "WHERE rowNum > $offset";
        }

        return $sql;
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB table.
     */
    public function renameTable($table, $newName)
    {
        return "sp_rename '$table', '$newName'";
    }

    /**
     * Builds a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB column.
     */
    public function renameColumn($table, $name, $newName)
    {
        return "sp_rename '$table.$name', '$newName', 'COLUMN'";
    }

    /**
     * Builds a SQL statement for changing the definition of a column.
     *
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The {@see \rock\db\QueryBuilder::getColumnType()} method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     * @return string the SQL statement for changing the definition of a column.
     */
    public function alterColumn($table, $column, $type)
    {
        $type = $this->getColumnType($type);
        $sql = 'ALTER TABLE ' . $this->connection->quoteTableName($table) . ' ALTER COLUMN '
            . $this->connection->quoteColumnName($column) . ' '
            . $this->getColumnType($type);

        return $sql;
    }

    /**
     * Builds a SQL statement for enabling or disabling integrity check.
     *
     * @param boolean $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @param string $table the table name. Defaults to empty string, meaning that no table will be changed.
     * @return string the SQL statement for checking integrity
     * @throws DbException if the table does not exist or there is no sequence associated with the table.
     */
    public function checkIntegrity($check = true, $schema = '', $table = '')
    {
        if ($schema !== '') {
            $table = "{$schema}.{$table}";
        }
        $table = $this->connection->quoteTableName($table);
        if ($this->connection->getTableSchema($table) === null) {
            throw new DbException("Table not found: {$table}.");
        }
        $enable = $check ? 'CHECK' : 'NOCHECK';

        return "ALTER TABLE {$table} {$enable} CONSTRAINT ALL";
    }

    /**
     * Returns an array of column names given model name
     *
     * @param string $modelClass name of the model class
     * @return array|null array of column names
     */
    protected function getAllColumnNames($modelClass = null)
    {
        if (!$modelClass) {
            return null;
        }
        /* @var $model \rock\db\ActiveRecord */
        $model = new $modelClass;
        $schema = $model->getTableSchema();
        $columns = array_keys($schema->columns);
        return $columns;
    }

    /**
     * @var boolean whether MSSQL used is old.
     */
    private $_oldMssql;

    /**
     * @return boolean whether the version of the MSSQL being used is older than 2012.
     * @throws DbException;
     */
    protected function isOldMssql()
    {
        if ($this->_oldMssql === null) {
            $pdo = $this->connection->getSlavePdo();
            $version = preg_split("/\./", $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION));
            $this->_oldMssql = $version[0] < 11;
        }
        return $this->_oldMssql;
    }

    /**
     * Builds SQL for IN condition
     *
     * @param string $operator
     * @param array $columns
     * @param Query $values
     * @param array $params
     * @return string SQL
     * @throws DbException
     */
    protected function buildSubqueryInCondition($operator, $columns, $values, array &$params)
    {
        if (is_array($columns)) {
            throw new DbException(__METHOD__ . ' is not supported by SQLite.');
        }
        return parent::buildSubqueryInCondition($operator, $columns, $values, $params);
    }
    /**
     * Builds SQL for IN condition
     *
     * @param string $operator
     * @param array $columns
     * @param array $values
     * @param array $params
     * @return string SQL
     */
    protected function buildCompositeInCondition($operator, array $columns, array $values, array &$params)
    {
        $quotedColumns = [];
        foreach ($columns as $i => $column) {
            $quotedColumns[$i] = strpos($column, '(') === false ? $this->connection->quoteColumnName($column) : $column;
        }
        $vss = [];
        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $i => $column) {
                if (isset($value[$column])) {
                    $phName = self::PARAM_PREFIX . count($params);
                    $params[$phName] = $value[$column];
                    $vs[] = $quotedColumns[$i] . ($operator === 'IN' ? ' = ' : ' != ') . $phName;
                } else {
                    $vs[] = $quotedColumns[$i] . ($operator === 'IN' ? ' IS' : ' IS NOT') . ' NULL';
                }
            }
            $vss[] = '(' . implode($operator === 'IN' ? ' AND ' : ' OR ', $vs) . ')';
        }
        return '(' . implode($operator === 'IN' ? ' OR ' : ' AND ', $vss) . ')';
    }
}
