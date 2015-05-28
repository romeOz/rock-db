<?php
namespace rock\db;

use rock\base\BaseException;
use rock\db\common\ActiveQueryInterface;
use rock\db\common\BaseActiveRecord;
use rock\db\common\ConnectionInterface;
use rock\db\common\DbException;
use rock\helpers\ArrayHelper;
use rock\helpers\Helper;
use rock\helpers\Inflector;
use rock\helpers\Instance;
use rock\helpers\ObjectHelper;
use rock\log\Log;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * Active Record implements the [Active Record design pattern](http://en.wikipedia.org/wiki/Active_record).
 * The premise behind Active Record is that an individual {@see \rock\db\ActiveRecord} object is associated with a specific
 * row in a database table. The object's attributes are mapped to the columns of the corresponding table.
 * Referencing an Active Record attribute is equivalent to accessing the corresponding table column for that record.
 *
 * As an example, say that the `Customer` ActiveRecord class is associated with the `customer` table.
 * This would mean that the class's `name` attribute is automatically mapped to the `name` column in `customer` table.
 * Thanks to Active Record, assuming the variable `$customer` is an object of type `Customer`, to get the value of
 * the `name` column for the table row, you can use the expression `$customer->name`.
 * In this example, Active Record is providing an object-oriented interface for accessing data stored in the database.
 * But Active Record provides much more functionality than this.
 *
 * To declare an ActiveRecord class you need to extend {@see \rock\db\ActiveRecord} and
 * implement the `tableName` method:
 *
 * ```php
 * <?php
 *
 * class Customer extends \rock\db\ActiveRecord
 * {
 *     public static function tableName()
 *     {
 *         return 'customer';
 *     }
 * }
 * ```
 *
 * The `tableName` method only has to return the name of the database table associated with the class.
 *
 * Class instances are obtained in one of two ways:
 *
 * * Using the `new` operator to create a new, empty object
 * * Using a method to fetch an existing record (or records) from the database
 *
 * Here is a short teaser how working with an ActiveRecord looks like:
 *
 * ```php
 * $user = new User();
 * $user->name = 'Tom';
 * $user->save();  // a new row is inserted into user table
 *
 * // the following will retrieve the user 'Chuck' from the database
 * $user = User::find()->where(['name' => 'Chuck'])->one();
 *
 * // this will get related records from orders table when relation is defined
 * $orders = $user->orders;
 * ```
 *
 * @method ActiveQuery hasMany(string $class, array $link) see {@see \rock\db\BaseActiveRecord::hasMany()} for more info
 * @method ActiveQuery hasOne(string $class, array $link) see {@see \rock\db\BaseActiveRecord::BaseActiveRecord::hasOne()} for more info
 */
class ActiveRecord extends BaseActiveRecord
{
    /**
     * The insert operation. This is mainly used when overriding {@see \rock\db\ActiveRecord::transactions()} to specify which operations are transactional.
     */
    const OP_INSERT = 0x01;
    /**
     * The update operation. This is mainly used when overriding {@see \rock\db\ActiveRecord::transactions()} to specify which operations are transactional.
     */
    const OP_UPDATE = 0x02;
    /**
     * The delete operation. This is mainly used when overriding {@see \rock\db\ActiveRecord::transactions()} to specify which operations are transactional.
     */
    const OP_DELETE = 0x04;
    /**
     * All three operations: insert, update, delete.
     * This is a shortcut of the expression: `OP_INSERT | OP_UPDATE | OP_DELETE`.
     */
    const OP_ALL = 0x07;

    private static $_alias = null;

    /**
     * Loads default values from database table schema
     *
     * To enable loading defaults for every newly created record, you can add a call to this method to {@see \rock\base\ObjectInterface::init()}:
     *
     * ```php
     * public function init()
     * {
     *     parent::init();
     *     $this->loadDefaultValues();
     * }
     * ```
     *
     * @param boolean $skipIfSet whether existing value should be preserved.
     * This will only set defaults for attributes that are `null`.
     * @return static the model instance itself.
     */
    public function loadDefaultValues($skipIfSet = true)
    {
        foreach ($this->getTableSchema()->columns as $column) {
            if ($column->defaultValue !== null && (!$skipIfSet || $this->{$column->name} === null)) {
                $this->{$column->name} = $column->defaultValue;
            }
        }
        return $this;
    }

    /**
     * Returns the database connection used by this AR class.
     * By default, the "db" application component is used as the database connection.
     * You may override this method if you want to use a different database connection.
     * @return Connection the database connection used by this AR class.
     */
    public static function getConnection()
    {
        return Instance::ensure('db', Connection::className());
    }

    /**
     * Creates an {@see \rock\db\ActiveQuery} instance with a given SQL statement.
     *
     * Note that because the SQL statement is already specified, calling additional
     * query modification methods (such as `where()`, `order()`) on the created {@see \rock\db\ActiveQuery}
     * instance will have no effect. However, calling `with()`, `asArray()` or `indexBy()` is
     * still fine.
     *
     * Below is an example:
     *
     * ```php
     * $customers = Customer::findBySql('SELECT * FROM customer')->all();
     * ```
     *
     * @param string $sql the SQL statement to be executed
     * @param array $params parameters to be bound to the SQL statement during execution.
     * @return ActiveQuery the newly created {@see \rock\db\ActiveQuery} instance
     */
    public static function findBySql($sql, $params = [])
    {
        $query = static::find();
        $query->sql = $sql;

        return $query->params($params);
    }

    /**
     * Finds ActiveRecord instance(s) by the given condition.
     * This method is internally called by {@see \rock\db\ActiveRecord::findOne()}
     * and {@see \rock\db\ActiveRecord::findAll()}.
     *
     * @param mixed $condition please refer to {@see \rock\db\ActiveRecord::findOne()} for the explanation of this parameter
     * @return ActiveQueryInterface
     * @throws DbException if there is no primary key defined
     * @internal
     */
    protected static function findByCondition($condition)
    {
        $query = static::find();

        if (!ArrayHelper::isAssociative($condition)) {
            // query by primary key
            $primaryKey = static::primaryKey();
            if (isset($primaryKey[0])) {
                $pk = $primaryKey[0];
                if (!empty($query->join) || !empty($query->joinWith)) {
                    $pk = static::tableName() . '.' . $pk;
                }
                $condition = [$pk => $condition];
            } else {
                throw new DbException('"' . get_called_class() . '" must have a primary key.');
            }
        }

        return $query->andWhere($condition);
    }

    /**
     * Updates the whole table using the provided attribute values and conditions.
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * Customer::updateAll(['status' => 1], 'status = 2');
     * ```
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the table
     * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return integer the number of rows updated
     */
    public static function updateAll($attributes, $condition = '', $params = [])
    {
        $command = static::getConnection()->createCommand();
        $command->update(static::tableName(), $attributes, $condition, $params);

        return $command->execute();
    }

    /**
     * Updates the whole table using the provided counter changes and conditions.
     * For example, to increment all customers' age by 1,
     *
     * ```php
     * Customer::updateAllCounters(['age' => 1]);
     * ```
     *
     * @param array $counters the counters to be updated (attribute name => increment value).
     * Use negative values if you want to decrement the counters.
     * @param string|array $condition the conditions that will be put in the WHERE part of the UPDATE SQL.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * Do not name the parameters as `:bp0`, `:bp1`, etc., because they are used internally by this method.
     * @return integer the number of rows updated
     */
    public static function updateAllCounters($counters, $condition = '', $params = [])
    {
        $n = 0;
        foreach ($counters as $name => $value) {
            $counters[$name] = new Expression("[[$name]]+:bp{$n}", [":bp{$n}" => $value]);
            $n++;
        }
        $command = static::getConnection()->createCommand();
        $command->update(static::tableName(), $counters, $condition, $params);

        return $command->execute();
    }

    /**
     * Deletes rows in the table using the provided conditions.
     * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * Customer::deleteAll('status = 3');
     * ```
     *
     * @param string|array $condition the conditions that will be put in the WHERE part of the DELETE SQL.
     * Please refer to {@see \rock\db\Query::where()} on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return integer the number of rows deleted
     */
    public static function deleteAll($condition = '', $params = [])
    {
        $command = static::getConnection()->createCommand();
        $command->delete(static::tableName(), $condition, $params);

        return $command->execute();
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new ActiveQuery(get_called_class());
    }

    /**
     * Declares the name of the database table associated with this AR class.
     * By default this method returns the class name as the table name by calling {@see \rock\helpers\Inflector::camel2id()}
     * with prefix {@see \rock\db\Connection::$tablePrefix}.
     * For example if {@see \rock\db\Connection::$tablePrefix} is 'tbl_', 'Customer' becomes 'tbl_customer',
     * and 'OrderItem' becomes 'tbl_order_item'. You may override this method
     * if the table is not named after this convention.
     *
     * @return string the table name
     */
    public static function tableName()
    {
        return '{{%' . Inflector::camel2id(ObjectHelper::basename(get_called_class()), '_') . '}}';
    }

    /**
     * Get table alias
     * @return string|null
     */
    public static function tableAlias()
    {
        $nameClass = get_called_class();
        if (isset(self::$_alias[$nameClass])) {
            return self::$_alias[$nameClass];
        }
        if (preg_match('/^(.*?)(?i:\s+as|)\s+([^ ]+)$/', static::tableName(), $matches)) { // with alias
            self::$_alias[$nameClass] = $matches[2];
        }

        return isset(self::$_alias[$nameClass]) ? self::$_alias[$nameClass] : null;
    }

    /**
     * Returns the schema information of the DB table associated with this AR class.
     *
     * @param ConnectionInterface|null $connection
     * @throws DbException
     * @return TableSchema the schema information of the DB table associated with this AR class.
     */
    public static function getTableSchema(ConnectionInterface $connection = null)
    {
        $nameTable = static::tableName();
        if (preg_match('/^(.*?)(?i:\s+as|)\s+([^ ]+)$/', static::tableName(), $matches)) { // with alias
            $nameTable = $matches[1];
        }
        if (!isset($connection)) {
            $connection = static::getConnection();
        }
        $default = $connection->enableQueryCache;
        $connection->enableQueryCache = false;
        $schema = $connection->getTableSchema($nameTable);
        $connection->enableQueryCache = $default;
        if ($schema !== null) {
            return $schema;
        } else {
            throw new DbException('The table does not exist: ' . static::tableName());
        }
    }

    /**
     * Returns the primary key name(s) for this AR class.
     * The default implementation will return the primary key(s) as declared
     * in the DB table that is associated with this AR class.
     *
     * If the DB table does not declare any primary key, you should override
     * this method to return the attributes that you want to use as primary keys
     * for this AR class.
     *
     * Note that an array should be returned even for a table with single primary key.
     *
     * @return string[] the primary keys of the associated database table.
     */
    public static function primaryKey()
    {
        return static::getTableSchema()->primaryKey;
    }

    /**
     * Returns the list of all attribute names of the model.
     * The default implementation will return all column names of the table associated with this AR class.
     * @return array list of attribute names.
     */
    public function attributes()
    {
        return array_keys(static::getTableSchema()->columns);
    }

    /**
     * Declares which DB operations should be performed within a transaction in different scenarios.
     * The supported DB operations are: {@see \rock\db\ActiveRecord::OP_INSERT} , {@see \rock\db\ActiveRecord::OP_UPDATE} and {@see \rock\db\ActiveRecord::OP_DELETE},
     * which correspond to the {@see \rock\db\ActiveRecord::insert()}, {@see \rock\db\ActiveRecord::update()} and {@see \rock\db\ActiveRecord::delete()} methods, respectively.
     * By default, these methods are NOT enclosed in a DB transaction.
     *
     * In some scenarios, to ensure data consistency, you may want to enclose some or all of them
     * in transactions. You can do so by overriding this method and returning the operations
     * that need to be transactional. For example,
     *
     * ```php
     * return [
     *     'admin' => self::OP_INSERT,
     *     'api' => self::OP_INSERT | self::OP_UPDATE | self::OP_DELETE,
     *     // the above is equivalent to the following:
     *     // 'api' => self::OP_ALL,
     *
     * ];
     * ```
     *
     * The above declaration specifies that in the "admin" scenario, the insert operation ({@see \rock\db\ActiveRecord::insert()})
     * should be done in a transaction; and in the "api" scenario, all the operations should be done
     * in a transaction.
     *
     * @return array the declarations of transactional operations. The array keys are scenarios names,
     * and the array values are the corresponding transaction operations.
     */
    public function transactions()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function populateRecord($record, $row, ConnectionInterface $connection = null)
    {
        $columns = static::getTableSchema($connection)->columns;
        foreach ($row as $name => $value) {
            if (isset($columns[$name])) {
                $row[$name] = $columns[$name]->phpTypecast($value);
            } elseif (is_array($value)) {
                $row[$name] = ArrayHelper::map(
                    $value,
                    function($value){
                        return Helper::toType($value);
                    },
                    true
                );
            }
        }
        parent::populateRecord($record, $row);
    }

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see \rock\components\Model::beforeValidate()} when `$runValidation` is true. If validation
     *    fails, it will skip the rest of the steps;
     * 2. call {@see \rock\components\Model::afterValidate()} when `$runValidation` is true.
     * 3. call {@see \rock\db\BaseActiveRecord::beforeSave()}. If the method returns false, it will skip the
     *    rest of the steps;
     * 4. insert the record into database. If this fails, it will skip the rest of the steps;
     * 5. call {@see \rock\db\BaseActiveRecord::afterSave()};
     *
     * In the above step 1, 2, 3 and 5, events {@see \rock\components\Model::EVENT_BEFORE_VALIDATE},
     * {@see \rock\db\BaseActiveRecord::EVENT_BEFORE_INSERT}, {@see \rock\db\BaseActiveRecord::EVENT_AFTER_INSERT} and {@see \rock\components\Model::EVENT_AFTER_VALIDATE}
     * will be raised by the corresponding methods.
     *
     * Only the {@see \rock\db\BaseActiveRecord::$dirtyAttributes}(changed attribute values) will be inserted into database.
     *
     * If the table's primary key is auto-incremental and is null during insertion,
     * it will be populated with the actual value after insertion.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer;
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param boolean $runValidation whether to perform validation before saving the record.
     * If the validation fails, the record will not be inserted into the database.
     * @param array $attributes list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @return boolean whether the attributes are valid and the record is inserted successfully.
     * @throws \Exception in case insert failed.
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            if (class_exists('\rock\log\Log')) {
                $message = BaseException::convertExceptionToString(new DbException('Model not inserted due to validation error.'));
                Log::info($message);
            }
            return false;
        }
        if (!$this->isTransactional(self::OP_INSERT)) {
            return $this->insertInternal($attributes);
        }

        $transaction = static::getConnection()->beginTransaction();
        try {
            $result = $this->insertInternal($attributes);
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }
            return $result;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Inserts an ActiveRecord into DB without considering transaction.
     * @param array $attributes list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @return boolean whether the record is inserted successfully.
     */
    protected function insertInternal($attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            foreach ($this->getPrimaryKey(true) as $key => $value) {
                $values[$key] = $value;
            }
        }
        $connection = static::getConnection();
        $command = $connection->createCommand()->insert($this->tableName(), $values);
        if (!$command->execute()) {
            return false;
        }
        $table = $this->getTableSchema($connection);
        if ($table->sequenceName !== null) {
            foreach ($table->primaryKey as $name) {
                if ($this->getAttribute($name) === null) {
                    $id = $table->columns[$name]->phpTypecast($connection->getLastInsertID($table->sequenceName));
                    $this->setAttribute($name, $id);
                    $values[$name] = $id;
                    break;
                }
            }
        }

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * Saves the changes to this active record into the associated database table.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see \rock\components\Model::beforeValidate()} when `$runValidation` is true. If validation
     *    fails, it will skip the rest of the steps;
     * 2. call {@see \rock\components\Model::afterValidate()} when `$runValidation` is true.
     * 3. call {@see \rock\db\BaseActiveRecord::beforeSave()}. If the method returns false, it will skip the
     *    rest of the steps;
     * 4. save the record into database. If this fails, it will skip the rest of the steps;
     * 5. call {@see \rock\db\BaseActiveRecord::afterSave()};
     *
     * In the above step 1, 2, 3 and 5, events {@see \rock\components\Model::EVENT_BEFORE_VALIDATE},
     * {@see \rock\db\BaseActiveRecord::EVENT_BEFORE_UPDATE}, {@see \rock\db\BaseActiveRecord::EVENT_AFTER_UPDATE} and {@see \rock\components\Model::EVENT_AFTER_VALIDATE}
     * will be raised by the corresponding methods.
     *
     * Only the {@see \rock\db\BaseActiveRecord::$dirtyAttributes}(changed attribute values) will be saved into database.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customer = Customer::findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->update();
     * ```
     *
     * Note that it is possible the update does not affect any row in the table.
     * In this case, this method will return 0. For this reason, you should use the following
     * code to check if update() is successful or not:
     *
     * ```php
     * if ($this->update() !== false) {
     *     // update successful
     * } else {
     *     // update failed
     * }
     * ```
     *
     * @param boolean $runValidation whether to perform validation before saving the record.
     * If the validation fails, the record will not be inserted into the database.
     * @param array $attributeNames list of attributes that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @return integer|boolean the number of rows affected, or false if validation fails
     * or {@see \rock\db\BaseActiveRecord::beforeSave()} stops the updating process.
     * @throws DbException if {@see \rock\db\BaseActiveRecord::optimisticLock()}(optimistic locking) is enabled and the data
     * being updated is outdated.
     * @throws \Exception in case update failed.
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            if (class_exists('\rock\log\Log')) {
                $message = BaseException::convertExceptionToString(new DbException('Model not updated due to validation error.'));
                Log::info($message);
            }
            return false;
        }

        if (!$this->isTransactional(self::OP_UPDATE)) {
            return $this->updateInternal($attributeNames);
        }

        $transaction = static::getConnection()->beginTransaction();
        try {
            $result = $this->updateInternal($attributeNames);
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }
            return $result;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see \rock\db\BaseActiveRecord::beforeDelete()}. If the method returns false, it will skip the
     *    rest of the steps;
     * 2. delete the record from the database;
     * 3. call {@see \rock\db\BaseActiveRecord::afterDelete()}.
     *
     * In the above step 1 and 3, events named {@see \rock\db\BaseActiveRecord::EVENT_BEFORE_DELETE} and {@see \rock\db\BaseActiveRecord::EVENT_AFTER_DELETE}
     * will be raised by the corresponding methods.
     *
     * @return integer|boolean the number of rows deleted, or false if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws DbException if {@see \rock\db\BaseActiveRecord::optimisticLock()}(optimistic locking) is enabled and the data
     * being deleted is outdated.
     * @throws \Exception in case delete failed.
     */
    public function delete()
    {
        if (!$this->isTransactional(self::OP_DELETE)) {
            return $this->deleteInternal();
        }

        $transaction = static::getConnection()->beginTransaction();
        try {
            $result = $this->deleteInternal();
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }
            return $result;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Deletes an ActiveRecord without considering transaction.
     *
     * @return integer|boolean the number of rows deleted, or false if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws DbException
     */
    protected function deleteInternal()
    {
        $result = false;
        if ($this->beforeDelete()) {
            // we do not check the return value of deleteAll() because it's possible
            // the record is already deleted in the database and thus the method will return 0
            $condition = $this->getOldPrimaryKey(true);
            $lock = $this->optimisticLock();
            if ($lock !== null) {
                $condition[$lock] = $this->$lock;
            }
            $result = $this->deleteAll($condition);
            if ($lock !== null && !$result) {
                throw new DbException('The object being deleted is outdated.');
            }
            $this->setOldAttributes(null);
            $this->afterDelete();
        }

        return $result;
    }

    /**
     * Returns a value indicating whether the given active record is the same as the current one.
     * The comparison is made by comparing the table names and the primary key values of the two active records.
     * If one of the records {@see \rock\db\BaseActiveRecord::$isNewRecord}(is new) they are also considered not equal.
     * @param ActiveRecord $record record to compare to
     * @return boolean whether the two active records refer to the same row in the same database table.
     */
    public function equals($record)
    {
        if ($this->isNewRecord || $record->isNewRecord) {
            return false;
        }

        return $this->tableName() === $record->tableName() && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    /**
     * Returns a value indicating whether the specified operation is transactional in the current {@see \rock\components\Model::$scenario}.
     * @param integer $operation the operation to check. Possible values are {@see \rock\db\ActiveRecord::OP_INSERT}, {@see \rock\db\ActiveRecord::OP_UPDATE} and {@see \rock\db\ActiveRecord::OP_DELETE}.
     * @return boolean whether the specified operation is transactional in the current {@see \rock\components\Model::$scenario}.
     */
    public function isTransactional($operation)
    {
        $scenario = $this->getScenario();
        $transactions = $this->transactions();

        return isset($transactions[$scenario]) && ($transactions[$scenario] & $operation);
    }
}