<?php
namespace rock\db;


use rock\components\ComponentsTrait;
use rock\components\ModelEvent;
use rock\db\common\AfterFindEvent;
use rock\db\common\CommonCacheTrait;
use rock\db\common\ConnectionInterface;
use rock\db\common\QueryInterface;
use rock\db\common\QueryTrait;
use rock\helpers\ArrayHelper;
use rock\helpers\Helper;
use rock\helpers\Instance;

/**
 * Query represents a SELECT SQL statement in a way that is independent of DBMS.
 *
 * Query provides a set of methods to facilitate the specification of different clauses
 * in a SELECT statement. These methods can be chained together.
 *
 * By calling {@see \rock\db\Query::createCommand()}, we can get a {@see \rock\db\Command} instance which can be further
 * used to perform/execute the DB query against a database.
 *
 * For example,
 *
 * ```php
 * $query = new Query;
 * // compose the query
 * $query->select('id, name')
 *     ->from('user')
 *     ->limit(10);
 * // build and execute the query
 * $rows = $query->all();
 * // alternatively, you can create DB command and execute it
 * $command = $query->createCommand();
 * // $command->sql returns the actual SQL
 * $rows = $command->queryAll();
 * ```
 */
class Query implements QueryInterface
{
    use ComponentsTrait {
        ComponentsTrait::__call as parentCall;
    }
    use QueryTrait;
    use CommonCacheTrait;

    /**
     * @event Event an event that is triggered after the record is created and populated with query result.
     */
    const EVENT_BEFORE_FIND = 'beforeFind';
    /**
     * @event Event an event that is triggered after the record is created and populated with query result.
     */
    const EVENT_AFTER_FIND = 'afterFind';

    /**
     * @var array the columns being selected. For example, `['id', 'name']`.
     * This is used to construct the SELECT clause in a SQL statement. If not set, it means selecting all columns.
     * @see select()
     */
    public $select = [];
    /**
     * @var string additional option that should be appended to the 'SELECT' keyword. For example,
     * in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used.
     */
    public $selectOption;
    /**
     * @var boolean whether to select distinct rows of data only. If this is set true,
     * the SELECT clause would be changed to SELECT DISTINCT.
     */
    public $distinct;
    /**
     * @var array the table(s) to be selected from. For example, `['user', 'post']`.
     * This is used to construct the FROM clause in a SQL statement.
     * @see from()
     */
    public $from = [];
    /**
     * @var array how to group the query results. For example, `['company', 'department']`.
     * This is used to construct the GROUP BY clause in a SQL statement.
     */
    public $groupBy = [];
    /**
     * @var array how to join with other tables. Each array element represents the specification
     * of one join which has the following structure:
     *
     * ```php
     * [$joinType, $tableName, $joinCondition]
     * ```
     *
     * For example,
     *
     * ```php
     * [
     *     ['INNER JOIN', 'user', 'user.id = author_id'],
     *     ['LEFT JOIN', 'team', 'team.id = team_id'],
     * ]
     * ```
     */
    public $join = [];
    /**
     * @var string|array the condition to be applied in the GROUP BY clause.
     * It can be either a string or an array. Please refer to {@see \rock\db\Query::where()} on how to specify the condition.
     */
    public $having;
    /**
     * @var array this is used to construct the UNION clause(s) in a SQL statement.
     * Each array element is an array of the following structure:
     *
     * - `query`: either a string or a {@see \rock\db\Query} object representing a query
     * - `all`: boolean, whether it should be `UNION ALL` or `UNION`
     */
    public $union = [];
    /**
     * @var integer
     */
    public $unionLimit;
    /** @var integer */
    public $unionOffset;
    /** @var array */
    public $unionOrderBy = [];
    /**
     * @var array list of query parameter values indexed by parameter placeholders.
     * For example, `[':name' => 'Dan', ':age' => 31]`.
     */
    public $params = [];
    /**
     * @var ConnectionInterface|Connection|string
     */
    protected $connection = 'db';

    /**
     * @param ConnectionInterface $connection DB/Sphinx connection instance
     * @return static the query object itself
     */
    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $this->calculateCacheParams($connection);
        return $this;
    }

    /**
     * @return Connection connection instance
     */
    public function getConnection()
    {
        $this->connection = Instance::ensure($this->connection, Connection::className());
        return $this->calculateCacheParams($this->connection);
    }

    /**
     * Creates a DB command that can be used to execute this query.
     *
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return Command the created DB command instance.
     */
    public function createCommand(ConnectionInterface $connection = null)
    {
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();
        $build = $connection->getQueryBuilder();
        $result = $build->build($this);
        $entities = $build->entities;
        list ($sql, $params) = $result;

        $command = $connection->createCommand($sql, $params);
        $command->entities = $entities;
        return $command;
    }

    /**
     * Prepares for building SQL.
     * This method is called by {@see \rock\db\QueryBuilder} when it starts to build SQL from a query object.
     * You may override this method to do some final preparation work when converting a query into a SQL statement.
     * @param QueryBuilder $builder
     * @return Query a prepared query instance which will be used by {@see \rock\db\QueryBuilder} to build the SQL
     */
    public function prepare($builder)
    {
        return $this;
    }

    /**
     * Starts a batch query.
     *
     * A batch query supports fetching data in batches, which can keep the memory usage under a limit.
     * This method will return a {@see \rock\db\BatchQueryResult} object which implements the `Iterator` interface
     * and can be traversed to retrieve the data in batches.
     *
     * For example,
     *
     * ```php
     * $query = (new Query)->from('user');
     * foreach ($query->batch() as $rows) {
     *     // $rows is an array of 10 or fewer rows from user table
     * }
     * ```
     *
     * @param integer $batchSize the number of records to be fetched in each batch.
     * @param ConnectionInterface $connection the database connection. If not set, the "db" application component will be used.
     * @return BatchQueryResult the batch query result. It implements the `Iterator` interface
     * and can be traversed to retrieve the data in batches.
     */
    public function batch($batchSize = 100, ConnectionInterface $connection = null)
    {
        return Instance::ensure([
            'class' => BatchQueryResult::className(),
            'query' => $this,
            'batchSize' => $batchSize,
            'connection' => isset($connection) ? $connection : $this->getConnection(),
            'each' => false,
        ]);
    }

    /**
     * Starts a batch query and retrieves data row by row.
     * This method is similar to {@see \rock\db\Query::batch()} except that in each iteration of the result,
     * only one row of data is returned. For example,
     *
     * ```php
     * $query = (new Query)->from('user');
     * foreach ($query->each() as $row) {
     * }
     * ```
     *
     * @param integer $batchSize the number of records to be fetched in each batch.
     * @param ConnectionInterface $connection the database connection. If not set, the "db" application component will be used.
     * @return BatchQueryResult the batch query result. It implements the `Iterator` interface
     * and can be traversed to retrieve the data in batches.
     */
    public function each($batchSize = 100, ConnectionInterface $connection = null)
    {
        return Instance::ensure([
            'class' => BatchQueryResult::className(),
            'query' => $this,
            'batchSize' => $batchSize,
            'connection' => isset($connection) ? $connection : $this->getConnection(),
            'each' => true,
        ]);
    }

    /**
     * Executes the query and returns a single row of result.
     *
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array|null the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
    public function one(ConnectionInterface $connection = null)
    {
        if (!$this->beforeFind()) {
            return null;
        }
        $command = $this->createCommand($connection);
        $row = $command->queryOne();
        if ($row !== null) {
            $row = $this->toSubattributes($row);
            $rows = $this->prepareResult([$row], $connection);
            return reset($rows) ?: null;
        } else {
            return null;
        }
    }

    /**
     * Executes the query and returns all results as an array.
     *
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(ConnectionInterface $connection = null)
    {
        if (!$this->beforeFind()) {
            return [];
        }
        $command = $this->createCommand($connection);
        $rows = $command->queryAll();
        return $this->prepareResult($this->toSubattributes($rows), $connection);
    }

    public function toSubattributes(array $rows, ConnectionInterface $connection = null)
    {
        if (empty($rows) || !$this->asSubattributes) {
            return $rows;
        }
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();

        return ArrayHelper::toMulti($rows, $connection->aliasSeparator, true);
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * This method is internally used to convert the data fetched from database
     * into the format as required by this query.
     *
     * @param array $rows the raw query result from database
     * @param ConnectionInterface|null  $connection
     * @return array the converted query result
     */
    public function prepareResult(array $rows, ConnectionInterface $connection = null)
    {
        if (!empty($rows)) {
            $rows = $this->typeCast($rows, $connection);
        }
        if ($this->indexBy === null) {
            $this->afterFind($rows);
            return $rows;
        }
        $result = [];
        foreach ($rows as $row) {
            if (is_string($this->indexBy)) {
                $key = $row[$this->indexBy];
            } else {
                $key = call_user_func($this->indexBy, $row);
            }
            $result[$key] = $row;
        }
        $this->afterFind($result);
        return $result;
    }

    /**
     * Returns the query result as a scalar value.
     * The value returned will be the first column in the first row of the query results.
     *
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return string|null the value of the first column in the first row of the query result.
     * False is returned if the query result is empty.
     */
    public function scalar(ConnectionInterface $connection = null)
    {
        if (!$this->beforeFind()) {
            return null;
        }

        $result = $this->typeCast($this->createCommand($connection)->queryScalar(), $connection);
        $this->afterFind($result);
        return $result;
    }

    /**
     * Executes the query and returns the first column of the result.
     *
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array the first column of the query result. An empty array is returned if the query results in nothing.
     */
    public function column(ConnectionInterface $connection = null)
    {
        if (!$this->beforeFind()) {
            return [];
        }
        if (!is_string($this->indexBy)) {
            $columns = $this->createCommand($connection)->queryColumn();
            $this->afterFind($columns);
            return $columns;
        }
        if (is_array($this->select) && count($this->select) === 1) {
            $this->select[] = $this->indexBy;
        }
        $rows = $this->createCommand($connection)->queryAll();
        $results = [];
        foreach ($rows as $row) {
            if (array_key_exists($this->indexBy, $row)) {
                $results[$row[$this->indexBy]] = reset($row);
            } else {
                $results[] = reset($row);
            }
        }
        $this->afterFind($results);
        return $results;
    }

    /**
     * Returns the number of records.
     *
     * @param string $q the COUNT expression. Defaults to '*'.
     * Make sure you properly quote column names in the expression.
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given (or null), the `db` application component will be used.
     * @return integer|string number of records. The result may be a string depending on the
     * underlying database engine and to support integer values higher than a 32bit PHP integer can handle.
     */
    public function count($q = '*', ConnectionInterface $connection = null)
    {
        return $this->queryScalar("COUNT($q)", $connection);
    }

    /**
     * Returns the sum of the specified column values.
     *
     * @param string $q the column name or expression.
     * Make sure you properly quote column names in the expression.
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return integer the sum of the specified column values.
     */
    public function sum($q, ConnectionInterface $connection = null)
    {
        return $this->queryScalar("SUM($q)", $connection);
    }

    /**
     * Returns the average of the specified column values.
     *
     * @param string $q the column name or expression.
     * Make sure you properly quote column names in the expression.
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return integer the average of the specified column values.
     */
    public function average($q, ConnectionInterface $connection = null)
    {
        return $this->queryScalar("AVG($q)", $connection);
    }

    /**
     * Returns the minimum of the specified column values.
     *
     * @param string $q the column name or expression.
     * Make sure you properly quote column names in the expression.
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return integer the minimum of the specified column values.
     */
    public function min($q, ConnectionInterface $connection = null)
    {
        return $this->queryScalar("MIN($q)", $connection);
    }

    /**
     * Returns the maximum of the specified column values.
     *
     * @param string $q the column name or expression.
     * Make sure you properly quote column names in the expression.
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return integer the maximum of the specified column values.
     */
    public function max($q, ConnectionInterface $connection = null)
    {
        return $this->queryScalar("MAX($q)", $connection);
    }

    /**
     * Returns a value indicating whether the query result contains any row of data.
     *
     * @param ConnectionInterface $connection the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return boolean whether the query result contains any row of data.
     */
    public function exists(ConnectionInterface $connection = null)
    {
        $select = $this->select;
        $this->select = [new Expression('1')];
        $command = $this->createCommand($connection);
        $this->select = $select;
        return $command->queryScalar() !== null;
    }

    /**
     * Queries a scalar value by setting {@see \rock\db\Query::$select} first.
     * Restores the value of select to make this query reusable.
     *
     * @param string|Expression $selectExpression
     * @param ConnectionInterface|null $connection
     * @return bool|string
     */
    protected function queryScalar($selectExpression, ConnectionInterface $connection = null)
    {
        $select = $this->select;
        $limit = $this->limit;
        $offset = $this->offset;

        $this->select = [$selectExpression];
        $this->limit = null;
        $this->offset = null;
        $command = $this->createCommand($connection);

        $this->select = $select;
        $this->limit = $limit;
        $this->offset = $offset;

        if (empty($this->groupBy) && empty($this->having) && empty($this->union) && !$this->distinct) {
            return $command->queryScalar();
        } else {
            return (new Query)->select([$selectExpression])
                ->from(['c' => $this])
                ->createCommand($command->connection)
                ->queryScalar();
        }
    }

    /**
     * Sets the SELECT part of the query.
     * @param string|array|SelectBuilder $columns the columns to be selected.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * Columns can be prefixed with table names (e.g. "user.id") and/or contain column aliases (e.g. "user.id AS user_id").
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     *
     * Note that if you are selecting an expression like `CONCAT(first_name, ' ', last_name)`, you should
     * use an array to specify the columns. Otherwise, the expression may be incorrectly split into several parts.
     *
     * When the columns are specified as an array, you may also use array keys as the column aliases (if a column
     * does not need alias, do not use a string key).
     *
     * @param string $option additional option that should be appended to the 'SELECT' keyword. For example,
     * in MySQL, the option 'SQL_CALC_FOUND_ROWS' can be used.
     * @return static the query object itself
     */
    public function select($columns, $option = null)
    {
        if ($columns instanceof SelectBuilder) {
            $columns = [$columns];
        }
        if (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->select = $columns;
        $this->selectOption = $option;
        return $this;
    }

    /**
     * Add more columns to the SELECT part of the query.
     *
     * Note, that if {@see \rock\db\Query::$select} has not been specified before, you should include `*` explicitly
     * if you want to select all remaining columns too:
     *
     * ```php
     * $query->addSelect(["*", "CONCAT(first_name, ' ', last_name) AS full_name"])->one();
     * ```
     *
     * @param string|array|SelectBuilder $columns the columns to add to the select.
     * @return static the query object itself
     * @see select()
     */
    public function addSelect($columns)
    {
        if ($columns instanceof SelectBuilder) {
            $columns = [$columns];
        }
        if (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->select = array_merge($this->select, $columns);

        return $this;
    }

    /**
     * Sets the value indicating whether to SELECT DISTINCT or not.
     * @param boolean $value whether to SELECT DISTINCT or not.
     * @return static the query object itself
     */
    public function distinct($value = true)
    {
        $this->distinct = $value;
        return $this;
    }

    /**
     * Sets the FROM part of the query.
     * 
     * @param string|array $tables the table(s) to be selected from. This can be either a string (e.g. `'user'`)
     * or an array (e.g. `['user', 'profile']`) specifying one or several table names.
     * Table names can contain schema prefixes (e.g. `'public.user'`) and/or table aliases (e.g. `'user u'`).
     * The method will automatically quote the table names unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     *
     * When the tables are specified as an array, you may also use the array keys as the table aliases
     * (if a table does not need alias, do not use a string key).
     *
     * Use a Query object to represent a sub-query. In this case, the corresponding array key will be used
     * as the alias for the sub-query.
     *
     * @return static the query object itself
     */
    public function from($tables)
    {
        if (!is_array($tables)) {
            $tables = preg_split('/\s*,\s*/', trim($tables), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->from = $tables;
        return $this;
    }

    /**
     * Returns alias of table
     * @param string $table
     * @param string|null $default default value
     * @return null
     */
    public static function alias($table, $default = null)
    {
        if (preg_match('/^(.*?)(?i:\s+as\s+|\s+)((?:{{%)?[\w\-_\.]+(?:}})?)$/', $table, $matches) && !empty($matches[2])) {
            return $matches[2];
        }

        return $default;
    }


    /**
     * Sets the WHERE part of the query.
     *
     * The method requires a $condition parameter, and optionally a $params parameter
     * specifying the values to be bound to the query.
     *
     * The `$condition` parameter should be either a string (e.g. `'id=1'`) or an array.
     *
     * @inheritdoc
     *
     * @param string|array $condition the conditions that should be put in the WHERE part.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see andWhere()
     * @see orWhere()
     * @see QueryInterface::where()
     */
    public function where($condition, array $params = [])
    {
        $this->where = $condition;
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param string|array $condition the new WHERE condition. Please refer to {@see \rock\db\Query::where()}
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andWhere($condition, array $params = [])
    {
        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where = ['and', $this->where, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new WHERE condition. Please refer to {@see \rock\db\Query::where()}
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see where()
     * @see andWhere()
     */
    public function orWhere($condition, array $params = [])
    {
        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where = ['or', $this->where, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Appends a JOIN part to the query.
     * 
     * The first parameter specifies what type of join it is.
     * @param string $type the type of join, such as INNER JOIN, LEFT JOIN.
     * @param string|array $table the table to be joined.
     *
     * Use string to represent the name of the table to be joined.
     * Table name can contain schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     *
     * Use array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a Query object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return Query the query object itself
     */
    public function join($type, $table, $on = '', array $params = [])
    {
        $this->join[] = [$type, $table, $on];
        return $this->addParams($params);
    }

    /**
     * Appends an INNER JOIN part to the query.
     * 
     * @param string|array $table the table to be joined.
     *
     * Use string to represent the name of the table to be joined.
     * Table name can contain schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     *
     * Use array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a Query object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return Query the query object itself
     */
    public function innerJoin($table, $on = '', array $params = [])
    {
        $this->join[] = ['INNER JOIN', $table, $on];
        return $this->addParams($params);
    }

    /**
     * Appends a LEFT OUTER JOIN part to the query.
     * 
     * @param string|array $table the table to be joined.
     *
     * Use string to represent the name of the table to be joined.
     * Table name can contain schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     *
     * Use array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a Query object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query
     * @return Query the query object itself
     */
    public function leftJoin($table, $on = '', array $params = [])
    {
        $this->join[] = ['LEFT JOIN', $table, $on];
        return $this->addParams($params);
    }

    /**
     * Appends a RIGHT OUTER JOIN part to the query.
     * 
     * @param string|array $table the table to be joined.
     *
     * Use string to represent the name of the table to be joined.
     * Table name can contain schema prefix (e.g. 'public.user') and/or table alias (e.g. 'user u').
     * The method will automatically quote the table name unless it contains some parenthesis
     * (which means the table is given as a sub-query or DB expression).
     *
     * Use array to represent joining with a sub-query. The array must contain only one element.
     * The value must be a Query object representing the sub-query while the corresponding key
     * represents the alias for the sub-query.
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query
     * @return Query the query object itself
     */
    public function rightJoin($table, $on = '', array $params = [])
    {
        $this->join[] = ['RIGHT JOIN', $table, $on];
        return $this->addParams($params);
    }

    /**
     * Sets the GROUP BY part of the query.
     * @param string|array $columns the columns to be grouped by.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * @return static the query object itself
     * @see addGroupBy()
     */
    public function groupBy($columns)
    {
        if (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->groupBy = $columns;
        return $this;
    }

    /**
     * Adds additional group-by columns to the existing ones.
     * @param string|array $columns additional columns to be grouped by.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * @return static the query object itself
     * @see groupBy()
     */
    public function addGroupBy($columns)
    {
        if (!is_array($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->groupBy = array_merge($this->groupBy, $columns);

        return $this;
    }

    /**
     * Sets the HAVING part of the query.
     * @param string|array $condition the conditions to be put after HAVING.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see andHaving()
     * @see orHaving()
     */
    public function having($condition, array $params = [])
    {
        $this->having = $condition;
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional HAVING condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param string|array $condition the new HAVING condition. Please refer to {@see \rock\db\Query::where()}
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see having()
     * @see orHaving()
     */
    public function andHaving($condition, array $params = [])
    {
        if ($this->having === null) {
            $this->having = $condition;
        } else {
            $this->having = ['and', $this->having, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional HAVING condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new HAVING condition. Please refer to {@see \rock\db\Query::where()}
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see having()
     * @see andHaving()
     */
    public function orHaving($condition, array $params = [])
    {
        if ($this->having === null) {
            $this->having = $condition;
        } else {
            $this->having = ['or', $this->having, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Appends a SQL statement using UNION operator.
     * @param string|Query $sql the SQL statement to be appended using UNION
     * @param boolean $all TRUE if using UNION ALL and FALSE if using UNION
     * @return static the query object itself
     */
    public function union($sql, $all = false)
    {
        $this->union[] = [ 'query' => $sql, 'all' => $all ];
        return $this;
    }

    /**
     * Adds ORDER BY columns to the union query.
     * @param string|array $columns the columns (and the directions) to be ordered by.
     * Columns can be specified in either a string (e.g. "id ASC, name DESC") or an array
     * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     * @return static the query object itself
     *
     * ```
     * (SELECT * FORM article) UNION (SELECT * FORM news) ORDER BY id DESC
     * ```
     */
    public function unionOrderBy(array $columns)
    {
        $columns = $this->normalizeOrderBy($columns);
        $this->unionOrderBy = array_merge($this->unionOrderBy, $columns);

        return $this;
    }

    /**
     * Sets the LIMIT part of the union query.
     * @param integer $limit
     * @return $this
     *
     * ```
     * (SELECT * FORM article) UNION (SELECT * FORM news) LIMIT 3
     * ```
     */
    public function unionLimit($limit)
    {
        $this->unionLimit = $limit;
        return $this;
    }

    /**
     * Sets the OFFSET part of the union query.
     * @param integer $offset
     * @return $this
     *
     * ```
     * (SELECT * FORM article) UNION (SELECT * FORM news) LIMIT 3,4
     * ```
     */
    public function unionOffset($offset)
    {
        $this->unionOffset = $offset;
        return $this;
    }

    /**
     * Sets the parameters to be bound to the query.
     * @param array $params list of query parameter values indexed by parameter placeholders.
     * For example, `[':name' => 'Dan', ':age' => 31]`.
     * @return static the query object itself
     * @see addParams()
     */
    public function params(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Adds additional parameters to be bound to the query.
     * @param array $params list of query parameter values indexed by parameter placeholders.
     * For example, `[':name' => 'Dan', ':age' => 31]`.
     * @return static the query object itself
     * @see params()
     */
    public function addParams(array $params)
    {
        if (!empty($params)) {
            if (empty($this->params)) {
                $this->params = $params;
            } else {
                foreach ($params as $name => $value) {
                    if (is_integer($name)) {
                        $this->params[] = $value;
                    } else {
                        $this->params[$name] = $value;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Creates a new Query object and copies its property values from an existing one.
     * The properties being copies are the ones to be used by query builders.
     * @param Query $from the source query object
     * @return Query the new Query object
     */
    public static function create($from)
    {
        return new self([
            'where' => $from->where,
            'limit' => $from->limit,
            'offset' => $from->offset,
            'orderBy' => $from->orderBy,
            'indexBy' => $from->indexBy,
            'select' => $from->select,
            'selectOption' => $from->selectOption,
            'distinct' => $from->distinct,
            'from' => $from->from,
            'groupBy' => $from->groupBy,
            'join' => $from->join,
            'having' => $from->having,
            'union' => $from->union,
            'params' => $from->params,
        ]);
    }

    public function getRawSql(ConnectionInterface $connection = null)
    {
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();

        list ($sql, $params) = $connection->getQueryBuilder()->build($this);
        return $connection->createCommand($sql, $params)->getRawSql();
    }

    public function refresh(ConnectionInterface $connection = null)
    {
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();
        $connection->getQueryBuilder()->build($this);
        return $this;
    }

    /**
     * This method is called when the AR object is created and populated with the query result.
     *
     * The default implementation will trigger an {@see \rock\db\common\BaseActiveRecord::EVENT_BEFORE_FIND} event.
     * When overriding this method, make sure you call the parent implementation to ensure the
     * event is triggered.
     */
    public function beforeFind()
    {
        $event = new ModelEvent;
        $this->trigger(self::EVENT_BEFORE_FIND, $event);
        return $event->isValid;
    }

    /**
     * This method is called when the AR object is created and populated with the query result.
     *
     * The default implementation will trigger an {@see \rock\db\common\BaseActiveRecord::EVENT_AFTER_FIND} event.
     * When overriding this method, make sure you call the parent implementation to ensure the
     * event is triggered.
     *
     * @param mixed $result the query result.
     */
    public function afterFind(&$result = null)
    {
        $event = new AfterFindEvent();
        $event->result = $result;
        $this->trigger(self::EVENT_AFTER_FIND, $event);
        $result = $event->result;
    }

    /**
     * @param array      $rows
     * @param ConnectionInterface $connection
     * @return array
     */
    protected function typeCast($rows, ConnectionInterface $connection = null)
    {
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();
        if ($connection->typeCast) {
            $rows = is_array($rows) ? ArrayHelper::toType($rows) : Helper::toType($rows);
        }

        return $rows;
    }
}