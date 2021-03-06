<?php
namespace rock\db;

use rock\db\common\ActiveQueryInterface;
use rock\db\common\ActiveQueryTrait;
use rock\db\common\ActiveRelationTrait;
use rock\db\common\BaseActiveRecord;
use rock\db\common\ConnectionInterface;
use rock\db\common\DbException;

/**
 * ActiveQuery represents a DB query associated with an Active Record class.
 *
 * An ActiveQuery can be a normal query or be used in a relational context.
 *
 * ActiveQuery instances are usually created by {@see \rock\db\common\ActiveRecordInterface::find()} and {@see \rock\db\ActiveRecord::findBySql()}.
 * Relational queries are created by {@see \rock\db\common\BaseActiveRecord::hasOne()} and {@see \rock\db\common\BaseActiveRecord::hasMany()}.
 *
 * Normal Query
 * ------------
 *
 * ActiveQuery mainly provides the following methods to retrieve the query results:
 *
 * - {@see \rock\db\ActiveQuery::one()}: returns a single record populated with the first row of data.
 * - {@see \rock\db\ActiveQuery::all()}: returns all records based on the query results.
 * - {@see \rock\db\ActiveQuery::count()}: returns the number of records.
 * - {@see \rock\db\ActiveQuery::sum()}: returns the sum over the specified column.
 * - {@see \rock\db\ActiveQuery::sum()}: returns the average over the specified column.
 * - {@see \rock\db\ActiveQuery::min()}: returns the min over the specified column.
 * - {@see \rock\db\ActiveQuery::max()}: returns the max over the specified column.
 * - {@see \rock\db\ActiveQuery::scalar()}: returns the value of the first column in the first row of the query result.
 * - {@see \rock\db\ActiveQuery::column()}: returns the value of the first column in the query result.
 * - {@see \rock\db\ActiveQuery::exists()}: returns a value indicating whether the query result has data or not.
 *
 * Because ActiveQuery extends from {@see \rock\db\Query}, one can use query methods, such as {@see \rock\db\Query::where()},
 * {@see \rock\db\common\QueryInterface::orderBy()} to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - {@see \rock\db\ActiveQuery::with()}: list of relations that this query should be performed with.
 * - {@see \rock\db\ActiveQuery::indexBy()}: the name of the column by which the query result should be indexed.
 * - {@see \rock\db\common\ActiveQueryInterface::asArray()}: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $customers = Customer::find()->with('orders')->asArray()->all();
 * ```
 *
 * Relational query
 * ----------------
 *
 * In relational context ActiveQuery represents a relation between two Active Record classes.
 *
 * Relational ActiveQuery instances are usually created by calling {@see \rock\db\common\BaseActiveRecord::hasOne()} and
 * {@see \rock\db\common\BaseActiveRecord::hasMany()}. An Active Record class declares a relation by defining
 * a getter method which calls one of the above methods and returns the created ActiveQuery object.
 *
 * A relation is specified by {@see \rock\db\ActiveRelationTrait::$link} which represents the association between columns
 * of different tables; and the multiplicity of the relation is indicated by {@see \rock\db\ActiveRelationTrait::$multiple}.
 *
 * If a relation involves a pivot table, it may be specified by {@see \rock\db\ActiveRelationTrait::via()} or {@see \rock\db\ActiveRecord::viaTable()} method.
 * These methods may only be called in a relational context. Same is true for {@see \rock\db\ActiveRelationTrait::inverseOf()}, which
 * marks a relation as inverse of another relation and {@see \rock\db\ActiveQuery::onCondition()} which adds a condition that
 * is to be added to relational query join condition.
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    /**
     * @event Event an event that is triggered when the query is initialized via {@see \rock\base\ObjectInterface::init()}.
     */
    const EVENT_INIT = 'init';

    /**
     * @var string the SQL statement to be executed for retrieving AR records.
     * This is set by {@see \rock\db\ActiveRecord::findBySql()}.
     */
    public $sql;
    /**
     * @var string|array the join condition to be used when this query is used in a relational context.
     * The condition will be used in the ON part when {@see \rock\db\ActiveQuery::joinWith()} is called.
     * Otherwise, the condition will be used in the WHERE part of a query.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @see onCondition()
     */
    public $on;
    /**
     * @var array a list of relations that this query should be joined with
     */
    public $joinWith;


    /**
     * Constructor.
     * @param string $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        parent::__construct($config);
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an {@see \rock\db\ActiveQuery::EVENT_INIT} event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        if ($this->connection instanceof ConnectionInterface) {
            return $this->calculateCacheParams($this->connection);
        }
        /** @var ActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        return $this->calculateCacheParams($modelClass::getConnection());
    }

    /**
     * Executes query and returns a single row of result.
     *
     * @param ConnectionInterface $connection the DB connection used to create the DB command.
     * If null, the DB connection returned by {@see \rock\db\ActiveQueryTrait::modleClass()} will be used.
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting
     * of {@see \rock\db\ActiveQueryTrait::$asArray},the query result may be either an array or an ActiveRecord object. Null will be returned
     * if the query results in nothing.
     */
    public function one(ConnectionInterface $connection = null)
    {
        // event before
        /** @var ActiveRecord $model */
        $model  = new $this->modelClass;
        if (!$model->beforeFind()) {
            return null;
        }
        return parent::one($connection);
    }

    /**
     * Executes query and returns all results as an array.
     *
     * @param ConnectionInterface $connection the DB connection used to create the DB command.
     * If null, the DB connection returned by {@see \rock\db\ActiveQueryTrait::modleClass()} will be used.
     * @return array|ActiveRecord[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(ConnectionInterface $connection = null)
    {
        // event before
        /** @var ActiveRecord $model */
        $model = new $this->modelClass;
        if (!$model->beforeFind()) {
            return [];
        }

        return parent::all($connection);
    }

    /**
     * @inheritdoc
     */
    public function prepareResult(array $rows, ConnectionInterface $connection = null)
    {
        if (empty($rows)) {
            return [];
        }
        /** @var BaseActiveRecord[] $models */
        $models = $this->createModels($rows);
        if (!empty($this->join) && $this->indexBy === null) {
            $models = $this->removeDuplicatedModels($models);
        }

        if (!empty($this->with)) {
            if (isset($this->queryBuild->entities)) {
                $this->queryBuild->entities = [];
            }
            $this->findWith($this->with, $models);
        }
        if (!$this->asArray) {
            foreach ($models as $model) {
                $model->afterFind();
            }
        } else {
            // event after
            /** @var ActiveRecord $selfModel */
            $selfModel = new $this->modelClass;
            $selfModel->afterFind($models);
        }
        return $models;
    }

    /**
     * @inheritdoc
     */
    public function prepare($builder)
    {
        // NOTE: because the same ActiveQuery may be used to build different SQL statements
        // (e.g. by ActiveDataProvider, one for count query, the other for row data query,
        // it is important to make sure the same ActiveQuery can be used to build SQL statements
        // multiple times.
        if (!empty($this->joinWith)) {
            $this->buildJoinWith();
            $this->joinWith = null;    // clean it up to avoid issue https://github.com/yiisoft/yii2/issues/2687
        }

        if (empty($this->from)) {
            /** @var ActiveRecord $modelClass */
            $modelClass = $this->modelClass;
            $tableName = $modelClass::tableName();
            $this->from = [$tableName];
        }

        if (empty($this->select) && !empty($this->join)) {
            foreach ((array) $this->from as $alias => $table) {
                if (is_string($alias)) {
                    $this->select = ["$alias.*"];
                } elseif (is_string($table)) {
                    if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $table, $matches)) {
                        $alias = $matches[2];
                    } else {
                        $alias = $table;
                    }
                    $this->select = ["$alias.*"];
                }
                break;
            }
        }

        if ($this->primaryModel === null) {
            // eager loading
            $query = Query::create($this);
        } else {
            // lazy loading of a relation
            $where = $this->where;

            if ($this->via instanceof self) {
                // via junction table
                $viaModels = $this->via->findPivotRows([$this->primaryModel]);
                $this->filterByModels($viaModels);
            } elseif (is_array($this->via)) {
                // via relation
                /* @var $viaQuery ActiveQuery */
                list($viaName, $viaQuery) = $this->via;
                if ($viaQuery->multiple) {
                    $viaModels = $viaQuery->all();
                    $this->primaryModel->populateRelation($viaName, $viaModels);
                } else {
                    $model = $viaQuery->one();
                    $this->primaryModel->populateRelation($viaName, $model);
                    $viaModels = $model === null ? [] : [$model];
                }
                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->primaryModel]);
            }

            $query = Query::create($this);
            $this->where = $where;
        }

        if (!empty($this->on)) {
            $query->andWhere($this->on);
        }

        return $query;
    }

    /**
     * Creates a DB command that can be used to execute this query.
     *
     * @param ConnectionInterface $connection the DB connection used to create the DB command.
     * If null, the DB connection returned by {@see \rock\db\ActiveQueryTrait::modleClass()} will be used.
     * @return Command the created DB command instance.
     */
    public function createCommand(ConnectionInterface $connection = null)
    {
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();

        $entities = [];
        if ($this->sql === null) {
            $build =  $connection->getQueryBuilder();
            $result = $build->build($this);
            $entities = $build->entities;
            $this->queryBuild = $build;
            list ($sql, $params) = $result;
        } else {
            $sql = $this->sql;
            $params = $this->params;
        }
        $command = $connection->createCommand($sql, $params);
        $command->entities = $entities;

        return $command;
    }

    /** @var  QueryBuilder */
    private $queryBuild;

    /**
     * Creates a DB command that can be used to execute this query.
     *
     * @param ConnectionInterface|null $connection the DB connection used to create the DB command.
     * If null, the DB connection returned by {@see \rock\db\ActiveQueryTrait::modleClass()} will be used.
     * @return Command the created DB command instance.
     */
    protected function createCommandInternal(ConnectionInterface $connection = null)
    {
        if (isset($connection)) {
            $this->setConnection($connection);
        }
        $connection = $this->getConnection();

        $entities = [];
        if ($this->sql === null) {
            $build =  $connection->getQueryBuilder();
            $result = $build->build($this);
            $entities = $build->entities;
            $this->queryBuild = $build;
            list ($sql, $params) = $result;
        } else {
            $sql = $this->sql;
            $params = $this->params;
        }
        $command = $connection->createCommand($sql, $params);
        $command->entities = $entities;

        return $command;
    }

    /**
     * @inheritdoc
     */
    protected function queryScalar($selectExpression, ConnectionInterface $connection = null)
    {
        if ($this->sql === null) {
            return parent::queryScalar($selectExpression, $connection);
        }
        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;
        if ($connection === null) {
                $connection = $modelClass::getConnection();
            }
        return (new Query)->select([$selectExpression])
            ->from(['c' => "({$this->sql})"])
            ->params($this->params)
            ->createCommand($connection)
            ->queryScalar();
    }

    /**
     * Joins with the specified relations.
     *
     * This method allows you to reuse existing relation definitions to perform JOIN queries.
     * Based on the definition of the specified relation(s), the method will append one or multiple
     * JOIN statements to the current query.
     *
     * If the `$eagerLoading` parameter is true, the method will also eager loading the specified relations,
     * which is equivalent to calling {@see \rock\db\common\ActiveQueryInterface::with()} using the specified relations.
     *
     * Note that because a JOIN query will be performed, you are responsible to disambiguate column names.
     *
     * This method differs from {@see \rock\db\common\ActiveQueryInterface::with()} in that it will build up and execute a JOIN SQL statement
     * for the primary table. And when `$eagerLoading` is true, it will call {@see \rock\db\common\ActiveQueryInterface::with()} in addition with the specified relations.
     *
     * @param string|array $with the relations to be joined. Each array element represents a single relation.
     * The array keys are relation names, and the array values are the corresponding anonymous functions that
     * can be used to modify the relation queries on-the-fly. If a relation query does not need modification,
     * you may use the relation name as the array value. Sub-relations can also be specified (see {@see \rock\db\common\ActiveQueryInterface::with()}).
     * For example,
     *
     * ```php
     * // find all orders that contain books, and eager loading "books"
     * Order::find()->joinWith('books', true, 'INNER JOIN')->all();
     * // find all orders, eager loading "books", and sort the orders and books by the book names.
     * Order::find()->joinWith([
     *     'books' => function ($query) {
     *         $query->orderBy('item.name');
     *     }
     * ])->all();
     * ```
     *
     * @param boolean|array $eagerLoading whether to eager load the relations specified in `$with`.
     * When this is a boolean, it applies to all relations specified in `$with`. Use an array
     * to explicitly list which relations in `$with` need to be eagerly loaded.
     * @param string|array $joinType the join type of the relations specified in `$with`.
     * When this is a string, it applies to all relations specified in `$with`. Use an array
     * in the format of `relationName => joinType` to specify different join types for different relations.
     * @return static the query object itself
     */
    public function joinWith($with, $eagerLoading = true, $joinType = 'LEFT JOIN')
    {
        $this->joinWith[] = [(array) $with, $eagerLoading, $joinType];

        return $this;
    }

    private function buildJoinWith()
    {
        $join = $this->join;
        $this->join = [];

        foreach ($this->joinWith as $config) {
            list ($with, $eagerLoading, $joinType) = $config;
            $this->joinWithRelations(new $this->modelClass, $with, $joinType);

            if (is_array($eagerLoading)) {
                foreach ($with as $name => $callback) {
                    if (is_integer($name)) {
                        if (!in_array($callback, $eagerLoading, true)) {
                            unset($with[$name]);
                        }
                    } elseif (!in_array($name, $eagerLoading, true)) {
                        unset($with[$name]);
                    }
                }
            } elseif (!$eagerLoading) {
                $with = [];
            }

            $this->with($with);
        }

        // remove duplicated joins added by joinWithRelations that may be added
        // e.g. when joining a relation and a via relation at the same time
        $uniqueJoins = [];
        foreach ($this->join as $j) {
            $uniqueJoins[serialize($j)] = $j;
        }
        $this->join = array_values($uniqueJoins);

        if (!empty($join)) {
            // append explicit join to joinWith()
            // https://github.com/yiisoft/yii2/issues/2880
            $this->join = empty($this->join) ? $join : array_merge($this->join, $join);
        }
    }

    /**
     * Inner joins with the specified relations.
     * This is a shortcut method to {@see \rock\db\ActiveQuery::joinWith()} with the join type set as "INNER JOIN".
     * Please refer to {@see \rock\db\ActiveQuery::joinWith()} for detailed usage of this method.
     * @param string|array $with the relations to be joined with
     * @param boolean|array $eagerLoading whether to eager loading the relations
     * @return static the query object itself
     * @see joinWith()
     */
    public function innerJoinWith($with, $eagerLoading = true)
    {
        return $this->joinWith($with, $eagerLoading, 'INNER JOIN');
    }

    /**
     * Modifies the current query by adding join fragments based on the given relations.
     * @param ActiveRecord $model the primary model
     * @param array $with the relations to be joined
     * @param string|array $joinType the join type
     */
    private function joinWithRelations($model, $with, $joinType)
    {
        $relations = [];

        foreach ($with as $name => $callback) {
            if (is_integer($name)) {
                $name = $callback;
                $callback = null;
            }

            $primaryModel = $model;
            $parent = $this;
            $prefix = '';
            while (($pos = strpos($name, '.')) !== false) {
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
                $fullName = $prefix === '' ? $name : "$prefix.$name";
                if (!isset($relations[$fullName])) {
                    $relations[$fullName] = $relation = $primaryModel->getRelation($name);
                    $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
                } else {
                    $relation = $relations[$fullName];
                }
                $primaryModel = new $relation->modelClass;
                $parent = $relation;
                $prefix = $fullName;
                $name = $childName;
            }

            $fullName = $prefix === '' ? $name : "$prefix.$name";
            if (!isset($relations[$fullName])) {
                $relations[$fullName] = $relation = $primaryModel->getRelation($name);
                if ($callback !== null) {
                    call_user_func($callback, $relation);
                }
                if (!empty($relation->joinWith)) {
                    $relation->buildJoinWith();
                }
                $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
            }
        }
    }

    /**
     * Returns the join type based on the given join type parameter and the relation name.
     * @param string|array $joinType the given join type(s)
     * @param string $name relation name
     * @return string the real join type
     */
    private function getJoinType($joinType, $name)
    {
        if (is_array($joinType) && isset($joinType[$name])) {
            return $joinType[$name];
        } else {
            return is_string($joinType) ? $joinType : 'INNER JOIN';
        }
    }

    /**
     * Returns the table name and the table alias for {@see \rock\db\ActiveQueryTrait::modleClass()}.
     * @param ActiveQuery $query
     * @return array the table name and the table alias.
     */
    private function getQueryTableName($query)
    {
        if (empty($query->from)) {
            /** @var ActiveRecord $modelClass */
            $modelClass = $query->modelClass;
            $tableName = $modelClass::tableName();
        } else {
            $tableName = '';
            foreach ($query->from as $alias => $tableName) {
                if (is_string($alias)) {
                    return [$tableName, $alias];
                } else {
                    break;
                }
            }
        }

        if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $tableName, $matches)) {
            $alias = $matches[2];
        } else {
            $alias = $tableName;
        }

        return [$tableName, $alias];
    }

    /**
     * Joins a parent query with a child query.
     * The current query object will be modified accordingly.
     * @param ActiveQuery $parent
     * @param ActiveQuery $child
     * @param string $joinType
     */
    private function joinWithRelation($parent, $child, $joinType)
    {
        $via = $child->via;
        $child->via = null;
        if ($via instanceof ActiveQuery) {
            // via table
            $this->joinWithRelation($parent, $via, $joinType);
            $this->joinWithRelation($via, $child, $joinType);
            return;
        } elseif (is_array($via)) {
            // via relation
            $this->joinWithRelation($parent, $via[1], $joinType);
            $this->joinWithRelation($via[1], $child, $joinType);
            return;
        }

        list ($parentTable, $parentAlias) = $this->getQueryTableName($parent);
        list ($childTable, $childAlias) = $this->getQueryTableName($child);

        if (!empty($child->link)) {

            if (strpos($parentAlias, '{{') === false) {
                $parentAlias = '{{' . $parentAlias . '}}';
            }
            if (strpos($childAlias, '{{') === false) {
                $childAlias = '{{' . $childAlias . '}}';
            }

            $on = [];
            foreach ($child->link as $childColumn => $parentColumn) {
                $parentOperand = strpos($parentColumn, '.') === false ? "$parentAlias.[[$parentColumn]]" : "[[$parentColumn]]";
                $childOperand = strpos($childColumn, '.') === false ? "$childAlias.[[$childColumn]]" : "[[$childColumn]]";
                $on[] = "$parentOperand = $childOperand";
            }
            $on = implode(' AND ', $on);
            if (!empty($child->on)) {
                $on = ['and', $on, $child->on];
            }
        } else {
            $on = $child->on;
        }
        $this->join($joinType, empty($child->from) ? $childTable : $child->from, $on);

        if (!empty($child->where)) {
            $this->andWhere($child->where);
        }
        if (!empty($child->having)) {
            $this->andHaving($child->having);
        }
        if (!empty($child->orderBy)) {
            $this->addOrderBy($child->orderBy);
        }
        if (!empty($child->groupBy)) {
            $this->addGroupBy($child->groupBy);
        }
        if (!empty($child->params)) {
            $this->addParams($child->params);
        }
        if (!empty($child->join)) {
            foreach ($child->join as $join) {
                $this->join[] = $join;
            }
        }
        if (!empty($child->union)) {
            foreach ($child->union as $union) {
                $this->union[] = $union;
            }
        }
    }

    /**
     * Sets the ON condition for a relational query.
     * The condition will be used in the ON part when {@see \rock\db\ActiveQuery::joinWith()} is called.
     * Otherwise, the condition will be used in the WHERE part of a query.
     *
     * Use this method to specify additional conditions when declaring a relation in the {@see \rock\db\ActiveRecord} class:
     *
     * ```php
     * public function getActiveUsers()
     * {
     *     return $this->hasMany(User::className(), ['id' => 'user_id'])->onCondition(['active' => true]);
     * }
     * ```
     *
     * @param string|array $condition the ON condition. Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     */
    public function onCondition($condition, array $params = [])
    {
        $this->on = $condition;
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional ON condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param string|array $condition the new ON condition. Please refer to {@see \rock\db\Query::where()}
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see onCondition()
     * @see orOnCondition()
     */
    public function andOnCondition($condition, array $params = [])
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['and', $this->on, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Adds an additional ON condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array $condition the new ON condition. Please refer to {@see \rock\db\Query::where()}
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return static the query object itself
     * @see onCondition()
     * @see andOnCondition()
     */
    public function orOnCondition($condition, array $params = [])
    {
        if ($this->on === null) {
            $this->on = $condition;
        } else {
            $this->on = ['or', $this->on, $condition];
        }
        $this->addParams($params);
        return $this;
    }

    /**
     * Specifies the pivot table for a relational query.
     *
     * Use this method to specify a pivot table when declaring a relation in the {@see \rock\db\ActiveRecord} class:
     *
     * ```php
     * public function getItems()
     * {
     *     return $this->hasMany(Item::className(), ['id' => 'item_id'])
     *                 ->viaTable('order_item', ['order_id' => 'id']);
     * }
     * ```
     *
     * @param string $tableName the name of the pivot table.
     * @param array $link the link between the pivot table and the table associated with {@see \rock\db\ActiveRelationTrait::$primaryModel}.
     * The keys of the array represent the columns in the pivot table, and the values represent the columns
     * in the {@see \rock\db\ActiveRelationTrait::$primaryModel} table.
     * @param callable $callable a PHP callback for customizing the relation associated with the pivot table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return static
     * @see via()
     */
    public function viaTable($tableName, $link, callable $callable = null)
    {
        $relation = new ActiveQuery(
            get_class($this->primaryModel),
            [
                'from' => [$tableName],
                'link' => $link,
                'multiple' => true,
                'asArray' => true,
            ]
        );
        $this->via = $relation;
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }

        return $this;
    }

    /**
     * Removes duplicated models by checking their primary key values.
     *
     * This method is mainly called when a join query is performed, which may cause duplicated rows being returned.
     * @param array $models the models to be checked
     * @return array the distinctive models
     * @throws DbException
     */
    private function removeDuplicatedModels(array $models)
    {
        $hash = [];
        /** @var ActiveRecord $class */
        $class = $this->modelClass;
        $pks = $class::primaryKey();

        if (count($pks) > 1) {
            // composite primary key
            foreach ($models as $i => $model) {
                $key = [];
                foreach ($pks as $pk) {
                    if (!isset($model[$pk])) {
                        // do not continue if the primary key is not part of the result set
                        break 2;
                    }
                    $key[] = $model[$pk];
                }
                $key = serialize($key);
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } else {
                    $hash[$key] = true;
                }
            }
        } elseif (empty($pks)) {
            throw new DbException("Primary key of '{$class}' can not be empty.");
        } else {
            // single column primary key
            $pk = reset($pks);
            foreach ($models as $i => $model) {
                if (!isset($model[$pk])) {
                    // do not continue if the primary key is not part of the result set
                    break;
                }
                $key = $model[$pk];
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } elseif ($key !== null) {
                    $hash[$key] = true;
                }
            }
        }

        return array_values($models);
    }
}